<?php

namespace PipelineConfigSpec\Internal;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class Manifest
{
    private array $data;
    private ?array $groupKeys = null;
    private ?array $variableSources = null;
    private ?array $phaseRules = null;

    public function __construct(string $rootPath, string $configDir = 'config')
    {
        $configDir = $this->normalizeConfigDir($configDir);
        $path = Path::join($rootPath, $configDir, 'config.manifest.yaml');
        if (!is_file($path)) {
            throw new \RuntimeException("config.manifest.yaml fehlt: {$path}");
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("config.manifest.yaml ungueltig: {$path}");
        }
        $this->data = $data;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function resolvePhaseKeys(string $pipeline, string $phase): array
    {
        $commonKeys = $this->expandPhaseRules($this->commonPhaseRules($phase));
        $specificKeys = $this->expandPhaseRules($this->pipelinePhaseRules($pipeline, $phase));
        $merged = array_merge($commonKeys, $specificKeys);
        return array_values(array_unique($merged));
    }

    public function pipelinePhaseErrors(string $pipeline, string $phase): array
    {
        $errors = [];
        if (!$this->hasPipeline($pipeline)) {
            $errors[] = "Unbekannte Pipeline: {$pipeline}";
        }
        if (!$this->hasPhase($phase)) {
            $errors[] = "Unbekannte Phase: {$phase}";
        }
        return $errors;
    }

    public function checkDisjoint(string $pipeline, string $phase): array
    {
        $common = $this->commonPhaseRules($phase);
        $specific = $this->pipelinePhaseRules($pipeline, $phase);
        if ($common === [] || $specific === []) {
            return [];
        }

        $commonKeys = $this->expandPhaseRules($common);
        $specificKeys = $this->expandPhaseRules($specific);
        $overlap = array_intersect($commonKeys, $specificKeys);
        $errors = [];
        foreach (array_values($overlap) as $key) {
            $errors[] = "Disjunktheitsverletzung: {$key} in common.{$phase} und {$pipeline}.{$phase}";
        }
        return $errors;
    }

    public function sourcesForKey(string $key): array
    {
        $sources = $this->variableSources();
        $value = $sources[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public function variableKeys(): array
    {
        $keys = [];
        foreach ($this->variableGroups() as $groupKeys) {
            $keys = array_merge($keys, $groupKeys);
        }
        return array_values(array_unique($keys));
    }

    private function pipelines(): array
    {
        $pipelines = $this->data['pipelines'] ?? [];
        return is_array($pipelines) ? $pipelines : [];
    }

    private function variableGroupsData(): array
    {
        $groups = $this->data['variable-groups'] ?? [];
        return is_array($groups) ? $groups : [];
    }

    private function phasesData(): array
    {
        $phases = $this->data['phases'] ?? [];
        return is_array($phases) ? $phases : [];
    }

    private function hasPipeline(string $pipeline): bool
    {
        if ($pipeline === 'common') {
            return false;
        }
        return array_key_exists($pipeline, $this->pipelines());
    }

    private function hasPhase(string $phase): bool
    {
        return array_key_exists($phase, $this->phaseRules());
    }

    private function commonPhaseRules(string $phase): array
    {
        $rules = $this->phaseRules()[$phase] ?? [];
        return is_array($rules) ? $rules : [];
    }

    private function pipelinePhaseRules(string $pipeline, string $phase): array
    {
        $pipelines = $this->pipelines();
        $pipelineRules = $pipelines[$pipeline] ?? [];
        if (!is_array($pipelineRules)) {
            return [];
        }
        $rules = $pipelineRules[$phase] ?? [];
        return is_array($rules) ? $rules : [];
    }

    private function expandPhaseRules(array $rules): array
    {
        $groups = $this->variableGroups();
        $expanded = [];
        foreach ($rules as $groupKey => $selector) {
            $keys = $this->expandRule((string) $groupKey, $selector, $groups);
            $expanded = array_merge($expanded, $keys);
        }
        return array_values(array_unique($expanded));
    }

    private function expandRule(string $groupKey, mixed $selector, array $groups): array
    {
        $knownKeys = $groups[$groupKey] ?? null;
        if ($knownKeys === null) {
            throw new \RuntimeException("Unbekannter group-key: {$groupKey}");
        }

        if ($selector === '*') {
            return $knownKeys;
        }
        if (!is_array($selector)) {
            throw new \RuntimeException("Variablenliste fehlt fuer group-key: {$groupKey}");
        }
        return $this->expandVariables($groupKey, $selector, $knownKeys);
    }

    private function expandVariables(string $groupKey, array $variables, array $knownKeys): array
    {
        $known = array_flip($knownKeys);
        $expanded = [];
        foreach ($variables as $entry) {
            $key = $this->requireString($entry, "Variablen-key fehlt in group-key: {$groupKey}");
            if (!isset($known[$key])) {
                throw new \RuntimeException("Unbekannter Variablen-key {$key} in group-key: {$groupKey}");
            }
            $expanded[] = $key;
        }
        return array_values(array_unique($expanded));
    }

    private function variableGroups(): array
    {
        if ($this->groupKeys !== null) {
            return $this->groupKeys;
        }

        $groups = [];
        foreach ($this->variableGroupsData() as $groupKey => $variables) {
            $groupKey = $this->requireString($groupKey, 'Gruppen-key fehlt');
            $groups[$groupKey] = $this->collectVariableKeys($variables);
        }

        $this->groupKeys = $groups;
        return $groups;
    }

    private function collectVariableKeys(mixed $variables): array
    {
        if (!is_array($variables)) {
            return [];
        }

        $keys = [];
        foreach ($variables as $key => $_entry) {
            $key = $this->requireString($key, 'Variablen-key fehlt');
            $keys[] = $key;
        }
        return array_values(array_unique($keys));
    }

    private function variableSources(): array
    {
        if ($this->variableSources !== null) {
            return $this->variableSources;
        }

        $sources = [];
        foreach ($this->variableGroupsData() as $variables) {
            if (!is_array($variables)) {
                continue;
            }
            foreach ($variables as $key => $entry) {
                $key = $this->requireString($key, 'Variablen-key fehlt');
                if (!is_array($entry)) {
                    continue;
                }
                $value = $entry['sources'] ?? [];
                if (is_array($value)) {
                    $sources[$key] = array_values(array_filter($value, 'is_string'));
                }
            }
        }

        $this->variableSources = $sources;
        return $sources;
    }

    private function phaseRules(): array
    {
        if ($this->phaseRules !== null) {
            return $this->phaseRules;
        }

        $rules = [];
        foreach ($this->phasesData() as $key => $phase) {
            $key = $this->requireString($key, 'Phasen-key fehlt');
            if ($phase === null) {
                $phase = [];
            }
            if (!is_array($phase)) {
                continue;
            }
            $rules[$key] = $phase;
        }

        $this->phaseRules = $rules;
        return $rules;
    }

    private function requireString(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException($message);
        }
        return trim($value);
    }

    private function normalizeConfigDir(string $configDir): string
    {
        $trimmed = trim($configDir, DIRECTORY_SEPARATOR);
        if ($trimmed === '') {
            return 'config';
        }
        return $trimmed;
    }
}
