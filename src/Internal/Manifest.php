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

    public function variables(): array
    {
        $variables = $this->data['variables'] ?? [];
        return is_array($variables) ? $variables : [];
    }

    public function resolvePhaseKeys(string $pipeline, string $phase): ?array
    {
        $pipelines = $this->data['pipelines'] ?? [];
        if (!is_array($pipelines)) {
            return null;
        }
        $common = $this->phaseList($pipelines['common'] ?? null, $phase);
        $specific = $this->phaseList($pipelines[$pipeline] ?? null, $phase);
        if ($common === null && $specific === null) {
            return null;
        }
        $commonKeys = $common !== null ? $this->expandRules($common) : [];
        $specificKeys = $specific !== null ? $this->expandRules($specific) : [];
        $merged = array_merge($commonKeys, $specificKeys);
        return array_values(array_unique($merged));
    }

    public function checkDisjoint(string $pipeline, string $phase): array
    {
        $pipelines = $this->data['pipelines'] ?? [];
        $common = $this->phaseList($pipelines['common'] ?? null, $phase);
        $specific = $this->phaseList($pipelines[$pipeline] ?? null, $phase);
        if ($common === null || $specific === null) {
            return [];
        }
        $commonKeys = $this->expandRules($common);
        $specificKeys = $this->expandRules($specific);
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

    private function phaseList(?array $pipeline, string $phase): ?array
    {
        if (!is_array($pipeline)) {
            return null;
        }
        $list = $pipeline[$phase] ?? null;
        if (!is_array($list)) {
            return null;
        }
        return $list;
    }

    private function expandRules(array $rules): array
    {
        $groups = $this->variableGroups();
        $expanded = [];
        foreach ($rules as $rule) {
            if (!is_string($rule) || $rule === '') {
                continue;
            }
            if (array_key_exists($rule, $groups)) {
                $expanded = array_merge($expanded, $groups[$rule]);
                continue;
            }
            $expanded[] = $rule;
        }
        return array_values(array_unique($expanded));
    }

    private function variableGroups(): array
    {
        if ($this->groupKeys !== null) {
            return $this->groupKeys;
        }
        $groups = [];
        foreach ($this->variables() as $group => $items) {
            if (!is_array($items)) {
                continue;
            }
            $keys = [];
            foreach ($items as $key => $_config) {
                if (is_string($key) && $key !== '') {
                    $keys[] = $key;
                }
            }
            $groups[$group] = array_values(array_unique($keys));
        }
        $this->groupKeys = $groups;
        return $groups;
    }

    private function variableSources(): array
    {
        if ($this->variableSources !== null) {
            return $this->variableSources;
        }
        $sources = [];
        foreach ($this->variables() as $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $key => $config) {
                if (!is_string($key) || !is_array($config)) {
                    continue;
                }
                $value = $config['sources'] ?? [];
                if (is_array($value)) {
                    $sources[$key] = array_values(array_filter($value, 'is_string'));
                }
            }
        }
        $this->variableSources = $sources;
        return $sources;
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
