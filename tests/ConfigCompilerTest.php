<?php

declare(strict_types=1);

namespace ConfigPipelineSpec\Tests;

use ConfigPipelineSpec\Config\ConfigCompiler;
use ConfigPipelineSpec\Config\Context;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ConfigCompilerTest extends TestCase
{
    public function testCompileWritesFilteredConfig(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->seedEnvFiles($root);
        $values = $this->compileValues($root);

        self::assertSame('dev', $values['PIPELINE'] ?? null);
        self::assertSame('runtime', $values['PHASE'] ?? null);
        self::assertSame('https://example.test', $values['APP_URL'] ?? null);
    }

    public function testCompileThrowsOnUnexpectedKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected key: EXTRA');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->seedEnvFiles($root);
        $this->writeEnv($root, '.env', "EXTRA=ignore\n");

        $compiler = new ConfigCompiler($root);
        $context = new Context('dev', 'runtime');
        $compiler->compile($context, false, $root . '/out/env.php');
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
        return $root;
    }

    private function writeManifest(string $root, array $manifest): void
    {
        $payload = Yaml::dump($manifest, 8, 2);
        $path = $root . '/config/env.manifest.yaml';
        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Failed to write manifest.');
        }
    }

    private function writeEnv(string $root, string $file, string $content): void
    {
        $path = $root . '/' . $file;
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Failed to write env file.');
        }
    }

    private function manifestData(): array
    {
        return [
            'variables' => [
                'context' => [
                    'PIPELINE' => [],
                    'PHASE' => [],
                ],
                'app' => [
                    'APP_URL' => [],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        'required' => ['PIPELINE', 'PHASE', 'APP_URL'],
                        'allowed' => ['context', 'app'],
                    ],
                ],
            ],
        ];
    }

    private function seedEnvFiles(string $root): void
    {
        $this->writeEnv($root, '.env.dev.runtime', "APP_URL=https://example.test\n");
    }

    private function compileValues(string $root): array
    {
        $compiler = new ConfigCompiler($root);
        $context = new Context('dev', 'runtime');
        $target = $root . '/out/env.php';
        $path = $compiler->compile($context, false, $target);
        return $this->readConfig($path);
    }

    private function readConfig(string $path): array
    {
        $values = require $path;
        return is_array($values) ? $values : [];
    }
}
