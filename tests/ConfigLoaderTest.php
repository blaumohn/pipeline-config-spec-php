<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\ConfigLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsFilesInOrder(): void
    {
        $root = $this->createRoot();
        $this->seedYamlFiles($root);

        $loader = new ConfigLoader($root);
        $snapshot = $loader->load('dev');

        self::assertSame('second', $snapshot->values()['APP_ENV'] ?? null);
        self::assertSame('local', $snapshot->values()['LOCAL'] ?? null);
        self::assertSame('https://example.test', $snapshot->values()['APP_URL'] ?? null);
    }

    public function testLoadOverridesAppliesVars(): void
    {
        $root = $this->createRoot();
        $loader = new ConfigLoader($root);

        $snapshot = $loader->loadOverrides([
            'SFTP_HOST' => 'sftp-server',
            'SFTP_PORT' => '22',
        ]);

        self::assertSame('sftp-server', $snapshot->values()['SFTP_HOST'] ?? null);
        self::assertSame('22', $snapshot->values()['SFTP_PORT'] ?? null);
        self::assertSame('cli', $snapshot->sources()['SFTP_HOST'] ?? null);
        self::assertSame('cli', $snapshot->sources()['SFTP_PORT'] ?? null);
        self::assertSame(['cli'], $snapshot->loadedFiles());
    }

    public function testLoadOverridesEmptyReturnsEmpty(): void
    {
        $root = $this->createRoot();
        $loader = new ConfigLoader($root);

        $snapshot = $loader->loadOverrides([]);

        self::assertSame([], $snapshot->values());
        self::assertSame([], $snapshot->loadedFiles());
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'config-pipeline-spec-' . uniqid('', true));
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        if (!mkdir(Path::join($root, 'pipeline-config'), 0775, true)) {
            throw new \RuntimeException('Failed to create pipeline-config directory.');
        }
        if (!mkdir(Path::join($root, '.local'), 0775, true)) {
            throw new \RuntimeException('Failed to create local directory.');
        }
        return $root;
    }

    private function seedYamlFiles(string $root): void
    {
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), "app:\n  APP_ENV: first\n  APP_URL: https://example.test\n");
        $this->writeYaml($root, Path::join('.local', 'dev.yaml'), "app:\n  APP_ENV: second\n  LOCAL: local\n");
    }

    private function writeYaml(string $root, string $file, string $content): void
    {
        $path = Path::join($root, $file);
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Failed to write yaml file.');
        }
    }
}
