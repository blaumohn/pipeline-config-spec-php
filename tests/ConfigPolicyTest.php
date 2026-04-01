<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\ConfigPolicy;
use PipelineConfigSpec\Internal\ConfigSnapshot;
use PipelineConfigSpec\Internal\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ConfigPolicyTest extends TestCase
{
    public function testValidConfigPasses(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $policy = new ConfigPolicy();
        $manifest = new Manifest($root);
        $snapshot = new ConfigSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
        ], [
            'PIPELINE' => 'cli',
            'PHASE' => 'cli',
        ], []);

        $errors = $policy->validate($manifest, 'dev', 'runtime', $snapshot);
        self::assertSame([], $errors);
    }

    public function testDisallowedKeyFails(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $policy = new ConfigPolicy();
        $manifest = new Manifest($root);
        $snapshot = new ConfigSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
            'EXTRA' => 'x',
        ], [
            'PIPELINE' => 'cli',
            'PHASE' => 'cli',
            'EXTRA' => 'cli',
        ], []);

        $errors = $policy->validate($manifest, 'dev', 'runtime', $snapshot);
        self::assertNotEmpty($errors);
    }

    public function testSourceMismatchFails(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variables' => [
                'context' => [
                    'PIPELINE' => [],
                    'PHASE' => [],
                ],
                'mail' => [
                    'SMTP_PASS' => [
                        'sources' => ['local'],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        'context' => '*',
                        'mail' => ['SMTP_PASS'],
                    ],
                ],
            ],
        ]);

        $policy = new ConfigPolicy();
        $manifest = new Manifest($root);
        $snapshot = new ConfigSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
            'SMTP_PASS' => 'secret',
        ], [
            'PIPELINE' => 'cli',
            'PHASE' => 'cli',
            'SMTP_PASS' => 'system',
        ], []);

        $errors = $policy->validate($manifest, 'dev', 'runtime', $snapshot);
        self::assertNotEmpty($errors);
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
        $path = $root . '/config/config.manifest.yaml';
        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Failed to write manifest.');
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
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => ['context' => '*'],
                ],
            ],
        ];
    }
}
