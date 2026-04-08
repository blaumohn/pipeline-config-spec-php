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
            'APP_URL' => 'https://example.test',
        ], [
            'APP_URL' => 'file',
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
            'APP_URL' => 'https://example.test',
            'EXTRA' => 'x',
        ], [
            'APP_URL' => 'file',
            'EXTRA' => 'file',
        ], []);

        $errors = $policy->validate($manifest, 'dev', 'runtime', $snapshot);
        self::assertNotEmpty($errors);
    }

    public function testSourceMismatchFails(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                [
                    'key' => 'mail',
                    'variables' => [
                        [
                            'key' => 'SMTP_PASS',
                            'sources' => ['local'],
                        ],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        [
                            'group-key' => 'mail',
                            'variables' => [
                                ['key' => 'SMTP_PASS'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $policy = new ConfigPolicy();
        $manifest = new Manifest($root);
        $snapshot = new ConfigSnapshot([
            'SMTP_PASS' => 'secret',
        ], [
            'SMTP_PASS' => 'system',
        ], []);

        $errors = $policy->validate($manifest, 'dev', 'runtime', $snapshot);
        self::assertNotEmpty($errors);
    }

    public function testEmptyPhaseWithoutManifestRulesPassesWhenSnapshotIsEmpty(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                [
                    'key' => 'app',
                    'variables' => [
                        ['key' => 'APP_URL'],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        [
                            'group-key' => 'app',
                            'select' => '*',
                        ],
                    ],
                ],
            ],
        ]);

        $policy = new ConfigPolicy();
        $manifest = new Manifest($root);
        $snapshot = new ConfigSnapshot([], [], []);

        $errors = $policy->validate($manifest, 'dev', 'setup', $snapshot);
        self::assertSame([], $errors);
    }

    private function createRoot(): string
    {
        $root = '/tmp/config-pipeline-spec-' . uniqid('', true);
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
            'variable-groups' => [
                [
                    'key' => 'app',
                    'variables' => [
                        ['key' => 'APP_URL'],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        [
                            'group-key' => 'app',
                            'select' => '*',
                        ],
                    ],
                ],
            ],
        ];
    }
}
