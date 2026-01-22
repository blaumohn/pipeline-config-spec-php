<?php

declare(strict_types=1);

namespace ConfigPipelineSpec\Tests;

use ConfigPipelineSpec\Config\ConfigLoader;
use ConfigPipelineSpec\Config\Context;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsDotenvFilesInOrderWithoutProfile(): void
    {
        $root = $this->createRoot();
        $this->seedEnvFiles($root);

        $loader = new ConfigLoader($root);
        $snapshot = $loader->load(new Context('dev', 'runtime'));

        self::assertSame('runtime_local', $snapshot->values()['KEY'] ?? null);
        $files = $snapshot->loadedFiles();
        self::assertSame([
            $root . '/.env',
            $root . '/.env.local',
            $root . '/.env.dev',
            $root . '/.env.dev.local',
            $root . '/.env.dev.runtime',
            $root . '/.env.dev.runtime.local',
        ], $files);
        self::assertNotContains($root . '/.env.dev.preview', $files);
        self::assertNotContains($root . '/.env.dev.preview.runtime', $files);
    }

    private function createRoot(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/config-pipeline-spec-' . uniqid('', true);
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        return $root;
    }

    private function writeEnv(string $root, string $file, string $content): void
    {
        $path = $root . '/' . $file;
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Failed to write env file.');
        }
    }

    private function seedEnvFiles(string $root): void
    {
        $files = [
            '.env' => "KEY=base\n",
            '.env.local' => "KEY=local\n",
            '.env.dev' => "KEY=dev\n",
            '.env.dev.local' => "KEY=dev_local\n",
            '.env.dev.runtime' => "KEY=runtime\n",
            '.env.dev.runtime.local' => "KEY=runtime_local\n",
            '.env.dev.preview' => "KEY=profile\n",
            '.env.dev.preview.runtime' => "KEY=profile_runtime\n",
        ];
        foreach ($files as $file => $content) {
            $this->writeEnv($root, $file, $content);
        }
    }
}
