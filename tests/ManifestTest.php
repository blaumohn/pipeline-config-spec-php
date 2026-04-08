<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ManifestTest extends TestCase
{
    public function testExpandsSelectAllGroup(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $manifest = new Manifest($root);
        $keys = $manifest->resolvePhaseKeys('dev', 'build');

        self::assertContains('APP_URL', $keys);
        self::assertContains('APP_ENV', $keys);
    }

    public function testExpandsPartialGroup(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                [
                    'key' => 'app',
                    'variables' => [
                        ['key' => 'APP_URL'],
                        ['key' => 'APP_ENV'],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'build' => [
                        [
                            'group-key' => 'app',
                            'variables' => [
                                ['key' => 'APP_URL'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $keys = $manifest->resolvePhaseKeys('common', 'build');

        self::assertContains('APP_URL', $keys);
        self::assertNotContains('APP_ENV', $keys);
    }

    public function testReturnsNullForUnknownPhase(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $manifest = new Manifest($root);
        self::assertNull($manifest->resolvePhaseKeys('dev', 'unknown'));
    }

    public function testDisjointPassesWhenNoOverlap(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $manifest = new Manifest($root);
        $errors = $manifest->checkDisjoint('dev', 'build');

        self::assertSame([], $errors);
    }

    public function testDisjointFailsOnOverlap(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                [
                    'key' => 'app',
                    'variables' => [
                        ['key' => 'APP_URL'],
                        ['key' => 'APP_ENV'],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'build' => [
                        [
                            'group-key' => 'app',
                            'variables' => [
                                ['key' => 'APP_URL'],
                            ],
                        ],
                    ],
                ],
                'dev' => [
                    'build' => [
                        [
                            'group-key' => 'app',
                            'variables' => [
                                ['key' => 'APP_URL'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $errors = $manifest->checkDisjoint('dev', 'build');

        self::assertNotEmpty($errors);
        self::assertStringContainsString('APP_URL', $errors[0]);
    }

    public function testUnknownGroupFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannter group-key: missing');

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
                    'build' => [
                        [
                            'group-key' => 'missing',
                            'select' => '*',
                        ],
                    ],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $manifest->resolvePhaseKeys('common', 'build');
    }

    public function testUnknownVariableFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannter Variablen-key APP_ENV in group-key: app');

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
                    'build' => [
                        [
                            'group-key' => 'app',
                            'variables' => [
                                ['key' => 'APP_ENV'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $manifest->resolvePhaseKeys('common', 'build');
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
                        ['key' => 'APP_ENV'],
                    ],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'build' => [
                        [
                            'group-key' => 'app',
                            'select' => '*',
                        ],
                    ],
                ],
                'dev' => [
                    'build' => [],
                ],
            ],
        ];
    }
}
