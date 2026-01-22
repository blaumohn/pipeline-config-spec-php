<?php

namespace ConfigPipelineSpec\Config;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Path;

final class ConfigLoader
{
    private string $rootPath;
    private Dotenv $dotenv;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->dotenv = new Dotenv();
    }

    public function load(Context $context, array $overrides = []): ConfigSnapshot
    {
        $values = [];
        $sources = [];
        $loadedFiles = [];

        foreach ($this->dotenvFiles($context) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            $parsed = $this->dotenv->parse($content, $file);
            foreach ($parsed as $key => $value) {
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

    private function dotenvFiles(Context $context): array
    {
        $values = [
            'pipeline' => $context->pipeline(),
            'phase' => $context->phase(),
        ];
        $files = [];
        foreach ($this->patterns() as $pattern) {
            $file = $this->expandPattern($pattern, $values);
            if ($file === null) {
                continue;
            }
            $files[] = Path::join($this->rootPath, $file);
        }
        return $files;
    }

    private function patterns(): array
    {
        return [
            '.env',
            '.env.local',
            '.env.{pipeline}',
            '.env.{pipeline}.local',
            '.env.{pipeline}.{phase}',
            '.env.{pipeline}.{phase}.local',
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

}
