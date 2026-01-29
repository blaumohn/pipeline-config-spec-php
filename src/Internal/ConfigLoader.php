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

    public function load(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        $values = [];
        $sources = [];
        $loadedFiles = [];

        foreach ($this->configFiles($pipeline, $phase) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $parsed = Yaml::parseFile($file);
            $data = $this->assertMapping($parsed, $file);
            foreach ($data as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $values[$key] = $value;
                $sources[$key] = $file;
            }
            $loadedFiles[] = $file;
        }

        foreach ($overrides as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $values[$key] = $value;
            $sources[$key] = 'cli';
        }

        return new ConfigSnapshot($values, $sources, $loadedFiles);
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
            $configDir . '/common.yaml',
            $configDir . '/{pipeline}.yaml',
            '.local/{pipeline}.yaml',
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
