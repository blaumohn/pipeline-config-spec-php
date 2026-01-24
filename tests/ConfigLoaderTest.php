<?php

declare(strict_types=1);

namespace ConfigPipelineSpec\Tests;

use ConfigPipelineSpec\Config\ConfigLoader;
use ConfigPipelineSpec\Config\Context;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsYamlFilesInOrder(): void
    {
        $root = $this->createRoot();
        $this->seedYamlFiles($root);

        $loader = new ConfigLoader($root);
        $snapshot = $loader->load(new Context('dev', 'runtime'));

        self::assertSame('runtime_local', $snapshot->values()['KEY'] ?? null);
        $files = $snapshot->loadedFiles();
        self::assertSame([
            $root . '/config/common.yaml',
            $root . '/config/dev.yaml',
            $root . '/.local/dev.yaml',
            $root . '/config/dev-runtime.yaml',
            $root . '/.local/dev-runtime.yaml',
        ], $files);
        self::assertNotContains($root . '/config/dev-preview.yaml', $files);
        self::assertNotContains($root . '/.local/dev-preview.yaml', $files);
    }

    private function createRoot(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/config-pipeline-spec-' . uniqid('', true);
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        return $root;
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

    private function seedYamlFiles(string $root): void
    {
        $files = [
            'config/common.yaml' => "KEY: base\n",
            'config/dev.yaml' => "KEY: dev\n",
            '.local/dev.yaml' => "KEY: dev_local\n",
            'config/dev-runtime.yaml' => "KEY: runtime\n",
            '.local/dev-runtime.yaml' => "KEY: runtime_local\n",
            'config/dev-preview.yaml' => "KEY: preview\n",
            '.local/dev-preview.yaml' => "KEY: preview_local\n",
        ];
        foreach ($files as $file => $content) {
            $this->writeYaml($root, $file, $content);
        }
    }
}
