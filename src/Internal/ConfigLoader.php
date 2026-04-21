<?php

namespace PipelineConfigSpec\Internal;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class ConfigLoader
{
    private string $rootPath;
    private string $configDir;

    public function __construct(string $rootPath, string $configDir = 'config')
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->configDir = $this->normalizeConfigDir($configDir);
    }

    public function load(string $pipeline, string $phase): ConfigSnapshot
    {
        $state = $this->emptyLoadState();

        foreach ($this->configFiles($pipeline, $phase) as $file) {
            $state = $this->mergeConfigFile($state, $file);
        }

        return $this->snapshotFromState($state);
    }

    public function loadOverrides(string $pipeline, string $phase, array $rawOverrides): ConfigSnapshot
    {
        $state = $this->emptyLoadState();
        $state = $this->mergeOverrideBranch($state, $rawOverrides, [$phase], $phase);
        $state = $this->mergeOverrideBranch(
            $state,
            $rawOverrides,
            [$pipeline, $phase],
            $pipeline . '.' . $phase
        );

        return $this->snapshotFromState($state);
    }

    private function mergeOverrideBranch(array $state, array $rawOverrides, array $path, string $patternId): array
    {
        $resolved = $this->resolveOverrideBranch($rawOverrides, $path, $patternId);
        if (!$resolved['found']) {
            return $state;
        }

        $state = $this->mergeEntries($state, $resolved['entries'], 'cli');
        $state['loadedFiles'][] = $patternId;

        return $state;
    }

    private function resolveOverrideBranch(array $rawOverrides, array $path, string $patternId): array
    {
        $node = $this->readNodeAtPath($rawOverrides, $path);
        if ($node === null) {
            return ['found' => false, 'entries' => []];
        }

        $entries = $this->assertOverrideMapping($node['value'], $patternId);
        return ['found' => true, 'entries' => $this->flattenGroups($entries)];
    }

    private function readNodeAtPath(array $rawOverrides, array $path): ?array
    {
        $node = $rawOverrides;
        foreach ($path as $segment) {
            if (!is_string($segment) || !is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return ['value' => $node];
    }

    private function assertOverrideMapping(mixed $data, string $patternId): array
    {
        if (!is_array($data)) {
            throw new \RuntimeException("Override-Struktur ungueltig fuer {$patternId}: Mapping erwartet.");
        }
        return $data;
    }

    private function configFiles(string $pipeline, string $phase): array
    {
        $values = [
            'pipeline' => $pipeline,
            'phase' => $phase,
        ];
        $files = [];
        foreach ($this->patterns() as $pattern) {
            $file = $this->expandPattern($pattern, $values);
            $files[] = Path::join($this->rootPath, $file);
        }
        return $files;
    }

    private function patterns(): array
    {
        $configDir = $this->configDir;

        return [
            $configDir . '/{phase}.yaml',
            '.local/{phase}.yaml',
            $configDir . '/{pipeline}-{phase}.yaml',
            '.local/{pipeline}-{phase}.yaml',
        ];
    }

    private function expandPattern(string $pattern, array $values): string
    {
        $replaced = $pattern;
        foreach ($values as $key => $value) {
            $replaced = str_replace('{' . $key . '}', $value, $replaced);
        }
        return $replaced;
    }

    private function flattenGroups(array $data): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (is_string($subKey)) {
                        $flat[$subKey] = $subValue;
                    }
                }
            } else {
                $flat[$key] = $value;
            }
        }
        return $flat;
    }

    private function emptyLoadState(): array
    {
        return [
            'values' => [],
            'sources' => [],
            'loadedFiles' => [],
        ];
    }

    private function mergeConfigFile(array $state, string $file): array
    {
        if (!is_file($file)) {
            return $state;
        }

        $entries = $this->loadFileEntries($file);
        $state = $this->mergeEntries($state, $entries, $file);
        $state['loadedFiles'][] = $file;

        return $state;
    }

    private function loadFileEntries(string $file): array
    {
        $parsed = Yaml::parseFile($file);
        $data = $this->assertMapping($parsed, $file);

        return $this->flattenGroups($data);
    }

    private function mergeEntries(array $state, array $entries, string $file): array
    {
        foreach ($entries as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $state['values'][$key] = $value;
            $state['sources'][$key] = $file;
        }

        return $state;
    }

    private function snapshotFromState(array $state): ConfigSnapshot
    {
        return new ConfigSnapshot(
            $state['values'],
            $state['sources'],
            $state['loadedFiles']
        );
    }

    private function assertMapping(mixed $data, string $file): array
    {
        if ($data === null) {
            return [];
        }
        if (!is_array($data)) {
            throw new \RuntimeException("Config-Datei ungueltig: {$file}");
        }
        return $data;
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
