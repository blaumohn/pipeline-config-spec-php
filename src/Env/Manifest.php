<?php

namespace EnvPipelineSpec\Env;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class Manifest
{
    private array $data;
    private ?array $groupKeys = null;
    private ?array $variableSources = null;

    public function __construct(string $rootPath)
    {
        $path = Path::join($rootPath, 'config', 'env.manifest.yaml');
        if (!is_file($path)) {
            throw new \RuntimeException("env.manifest.yaml fehlt: {$path}");
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("env.manifest.yaml ungueltig: {$path}");
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

    public function resolvePhaseConfig(Context $context): ?array
    {
        $pipelines = $this->data['pipelines'] ?? [];
        if (!is_array($pipelines)) {
            return null;
        }
        $common = $this->pipelinePhase($pipelines['common'] ?? null, $context->phase());
        $specific = $this->pipelinePhase($pipelines[$context->pipeline()] ?? null, $context->phase());
        if ($common === null && $specific === null) {
            return null;
        }
        return $this->mergePhaseConfig($common, $specific);
    }

    public function expandAllowed(array $allowed): array
    {
        return $this->expandRules($allowed);
    }

    public function expandRequired(array $required): array
    {
        $expanded = $this->expandRules($required);
        return array_values(array_filter($expanded, fn (string $rule) => !str_contains($rule, '*')));
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

    private function mergePhaseConfig(?array $base, ?array $override): array
    {
        $base = is_array($base) ? $base : [];
        $override = is_array($override) ? $override : [];
        return [
            'required' => $this->mergeList($base['required'] ?? [], $override['required'] ?? []),
            'allowed' => $this->mergeList($base['allowed'] ?? [], $override['allowed'] ?? []),
        ];
    }

    private function mergeList(array $left, array $right): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $value) {
            if (is_string($value) && $value !== '') {
                $merged[] = $value;
            }
        }
        return array_values(array_unique($merged));
    }

    private function pipelinePhase(?array $pipeline, string $phase): ?array
    {
        if (!is_array($pipeline)) {
            return null;
        }
        $phaseConfig = $pipeline[$phase] ?? null;
        return is_array($phaseConfig) ? $phaseConfig : null;
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
}
