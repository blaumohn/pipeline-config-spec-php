<?php

namespace ConfigPipelineSpec\Config;

final class Config
{
    private string $rootPath;
    private array $values;

    public function __construct(string $rootPath, array $values = [])
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->values = $values;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        return $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            return $default;
        }
        return (int) $value;
    }

    public function requireString(string $key): string
    {
        $value = $this->get($key, null);
        if ($value === null || trim((string) $value) === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return (string) $value;
    }

    public function requireInt(string $key): int
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return (int) $value;
    }

    public function requireBool(string $key): bool
    {
        $value = $this->get($key, null);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function basePath(): string
    {
        $value = trim((string) $this->get('APP_BASE_PATH', ''));
        if ($value === '' || $value === '/') {
            return '';
        }
        return '/' . trim($value, '/');
    }

}
