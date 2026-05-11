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

    public function __construct(string $rootPath, string $configDir = 'pipeline-config')
    {
        $this->rootPath = Path::normalize($rootPath);
        $this->configDir = $this->normalizeConfigDir($configDir);
    }

    public function load(string $pipeline): ConfigSnapshot
    {
        $state = $this->emptyLoadState();

        foreach ($this->configFiles($pipeline) as $file) {
            $state = $this->mergeConfigFile($state, $file);
        }

        return $this->snapshotFromState($state);
    }

    public function loadOverrides(array $overrides): ConfigSnapshot
    {
        $state = $this->emptyLoadState();

        if ($overrides !== []) {
            $state = $this->mergeEntries($state, $overrides, 'cli');
            $state['loadedFiles'][] = 'cli';
        }

        return $this->snapshotFromState($state);
    }

    private function configFiles(string $pipeline): array
    {
        $files = [];
        foreach ($this->patterns($pipeline) as $pattern) {
            $files[] = Path::join($this->rootPath, $pattern);
        }
        return $files;
    }

    private function patterns(string $pipeline): array
    {
        return [
            Path::join($this->configDir, $pipeline . '.yaml'),
            Path::join('.local', $pipeline . '.yaml'),
        ];
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
        if ($configDir === '') {
            return 'pipeline-config';
        }
        $normalized = trim(Path::normalize($configDir), '/');
        return $normalized !== '' ? $normalized : 'pipeline-config';
    }
}
