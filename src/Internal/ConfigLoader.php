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
        $generic = $this->collectOverrides($rawOverrides, null, $phase);
        $specific = $this->collectOverrides($rawOverrides, $pipeline, $phase);
        $merged = array_merge($generic, $specific);

        $values = array_column($merged, 'value', 'var');
        $sources = array_fill_keys(array_keys($values), 'cli');
        return new ConfigSnapshot($values, $sources, []);
    }

    private function collectOverrides(array $rawOverrides, ?string $pipeline, string $phase): array
    {
        $collected = [];
        foreach ($rawOverrides as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $parsed = $this->parseOverrideKey($key);
            if ($parsed['pipeline'] !== $pipeline || $parsed['phase'] !== $phase) {
                continue;
            }
            $collected[] = ['var' => $parsed['var'], 'value' => $value];
        }
        return $collected;
    }

    private function parseOverrideKey(string $key): array
    {
        $parts = explode('.', $key);
        if (count($parts) === 3) {
            return ['pipeline' => null, 'phase' => $parts[0], 'group' => $parts[1], 'var' => $parts[2]];
        }
        if (count($parts) === 4) {
            return ['pipeline' => $parts[0], 'phase' => $parts[1], 'group' => $parts[2], 'var' => $parts[3]];
        }
        throw new \RuntimeException(
            "Ungültiger Override-Schlüssel (erwartet phase.gruppe.var oder pipeline.phase.gruppe.var): {$key}"
        );
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
