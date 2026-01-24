<?php

namespace ConfigPipelineSpec\Config;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function load(Context $context, array $overrides = []): ConfigSnapshot
    {
        $values = [];
        $sources = [];
        $loadedFiles = [];

        foreach ($this->configFiles($context) as $file) {
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

    private function configFiles(Context $context): array
    {
        $values = [
            'pipeline' => $context->pipeline(),
            'phase' => $context->phase(),
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
        return [
            'config/common.yaml',
            'config/{pipeline}.yaml',
            '.local/{pipeline}.yaml',
            'config/{pipeline}-{phase}.yaml',
            '.local/{pipeline}-{phase}.yaml',
        ];
    }

    private function expandPattern(string $pattern, array $values): ?string
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

}
