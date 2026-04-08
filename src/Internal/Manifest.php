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

    public function resolvePhaseKeys(string $pipeline, string $phase): ?array
    {
        $pipelines = $this->pipelines();
        $common = $this->phaseRules($pipelines['common'] ?? null, $phase);
        $specific = $this->phaseRules($pipelines[$pipeline] ?? null, $phase);
        if ($common === null && $specific === null) {
            return null;
        }

        $commonKeys = $this->expandPhaseRules($common ?? []);
        $specificKeys = $this->expandPhaseRules($specific ?? []);
        $merged = array_merge($commonKeys, $specificKeys);
        return array_values(array_unique($merged));
    }

    public function checkDisjoint(string $pipeline, string $phase): array
    {
        $pipelines = $this->pipelines();
        $common = $this->phaseRules($pipelines['common'] ?? null, $phase);
        $specific = $this->phaseRules($pipelines[$pipeline] ?? null, $phase);
        if ($common === null || $specific === null) {
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

    private function phaseRules(mixed $pipeline, string $phase): ?array
    {
        if (!is_array($pipeline)) {
            return null;
        }
        $rules = $pipeline[$phase] ?? null;
        if (!is_array($rules)) {
            return null;
        }
        return $rules;
    }

    private function expandPhaseRules(array $rules): array
    {
        $groups = $this->variableGroups();
        $expanded = [];
        foreach ($rules as $rule) {
            $keys = $this->expandRule($rule, $groups);
            $expanded = array_merge($expanded, $keys);
        }
        return array_values(array_unique($expanded));
    }

    private function expandRule(mixed $rule, array $groups): array
    {
        if (!is_array($rule)) {
            return [];
        }

        $groupKey = $this->requireString($rule['group-key'] ?? null, 'group-key fehlt');
        $knownKeys = $groups[$groupKey] ?? null;
        if ($knownKeys === null) {
            throw new \RuntimeException("Unbekannter group-key: {$groupKey}");
        }

        $selectAll = ($rule['select'] ?? null) === '*';
        $variables = $rule['variables'] ?? null;
        if ($selectAll) {
            return $knownKeys;
        }
        if (!is_array($variables)) {
            throw new \RuntimeException("Variablenliste fehlt fuer group-key: {$groupKey}");
        }
        return $this->expandVariables($groupKey, $variables, $knownKeys);
    }

    private function expandVariables(string $groupKey, array $variables, array $knownKeys): array
    {
        $known = array_flip($knownKeys);
        $expanded = [];
        foreach ($variables as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $this->requireString($entry['key'] ?? null, "Variablen-key fehlt in group-key: {$groupKey}");
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
        foreach ($this->variableGroupsData() as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupKey = $this->requireString($group['key'] ?? null, 'Gruppen-key fehlt');
            $groups[$groupKey] = $this->collectVariableKeys($group);
        }

        $this->groupKeys = $groups;
        return $groups;
    }

    private function collectVariableKeys(array $group): array
    {
        $variables = $group['variables'] ?? [];
        if (!is_array($variables)) {
            return [];
        }

        $keys = [];
        foreach ($variables as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $this->requireString($entry['key'] ?? null, 'Variablen-key fehlt');
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
        foreach ($this->variableGroupsData() as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($this->variableEntries($group) as $entry) {
                $key = $this->requireString($entry['key'] ?? null, 'Variablen-key fehlt');
                $value = $entry['sources'] ?? [];
                if (is_array($value)) {
                    $sources[$key] = array_values(array_filter($value, 'is_string'));
                }
            }
        }

        $this->variableSources = $sources;
        return $sources;
    }

    private function variableEntries(array $group): array
    {
        $variables = $group['variables'] ?? [];
        return is_array($variables) ? $variables : [];
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
