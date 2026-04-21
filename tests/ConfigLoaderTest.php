<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsFilesInOrder(): void
    {
        $root = $this->createRoot();
        $this->seedYamlFiles($root);

        $loader = new ConfigLoader($root);
        $snapshot = $loader->load('dev', 'runtime');

        self::assertSame('second', $snapshot->values()['APP_ENV'] ?? null);
        self::assertSame('local', $snapshot->values()['LOCAL'] ?? null);
        self::assertSame('https://example.test', $snapshot->values()['APP_URL'] ?? null);
    }

    public function testLoadsSystemLayerForRequestedKeys(): void
    {
        $root = $this->createRoot();
        $loader = new ConfigLoader($root);
        putenv('IP_SALT=test-salt');

        $snapshot = $loader->loadSystem(['IP_SALT']);
        putenv('IP_SALT');

        self::assertSame('test-salt', $snapshot->values()['IP_SALT'] ?? null);
        self::assertSame('system', $snapshot->sources()['IP_SALT'] ?? null);
    }

    private function createRoot(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/config-pipeline-spec-' . uniqid('', true);
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        if (!mkdir($root . '/config', 0775, true) && !is_dir($root . '/config')) {
            throw new \RuntimeException('Failed to create config directory.');
        }
        if (!mkdir($root . '/.local', 0775, true) && !is_dir($root . '/.local')) {
            throw new \RuntimeException('Failed to create local directory.');
        }
        return $root;
    }

    private function seedYamlFiles(string $root): void
    {
        $this->writeYaml($root, 'config/runtime.yaml', "APP_ENV: first\n");
        $this->writeYaml($root, '.local/runtime.yaml', "LOCAL: local\n");
        $this->writeYaml($root, 'config/dev-runtime.yaml', "APP_URL: https://example.test\n");
        $this->writeYaml($root, '.local/dev-runtime.yaml', "APP_ENV: second\n");
    }

    private function writeYaml(string $root, string $file, string $content): void
    {
        $path = $root . '/' . $file;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Failed to write yaml file.');
        }
    }
}
