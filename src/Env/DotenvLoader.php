<?php

namespace EnvPipelineSpec\Env;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Path;

final class DotenvLoader
{
    private string $rootPath;
    private Dotenv $dotenv;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->dotenv = new Dotenv();
    }

    public function load(Context $context, array $overrides = []): EnvSnapshot
    {
        $values = $this->systemEnv();
        $sources = $this->systemSources($values);
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

        $this->populateEnv($values);

        return new EnvSnapshot($values, $sources, $loadedFiles);
    }

    private function dotenvFiles(Context $context): array
    {
        $pipeline = $context->pipeline();
        $profile = $context->profile();

        $files = [
            Path::join($this->rootPath, '.env'),
            Path::join($this->rootPath, '.env.local'),
            Path::join($this->rootPath, '.env.' . $pipeline),
            Path::join($this->rootPath, '.env.' . $pipeline . '.local'),
        ];

        if ($profile !== null && $profile !== '') {
            $files[] = Path::join($this->rootPath, '.env.' . $pipeline . '.' . $profile);
            $files[] = Path::join($this->rootPath, '.env.' . $pipeline . '.' . $profile . '.local');
        }

        return $files;
    }

    private function systemEnv(): array
    {
        $vars = getenv();
        if (!is_array($vars)) {
            return [];
        }
        return $vars;
    }

    private function systemSources(array $values): array
    {
        $sources = [];
        foreach ($values as $key => $_value) {
            $sources[$key] = 'system';
        }
        return $sources;
    }

    private function populateEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            putenv($key . '=' . $value);
        }
    }
}
