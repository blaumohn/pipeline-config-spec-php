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
        $manifest = $this->manifest($this->manifestData());
        $keys = $manifest->resolvePhaseKeys('dev', 'build');

        self::assertContains('APP_URL', $keys);
        self::assertContains('APP_ENV', $keys);
    }

    public function testExpandsPartialGroup(): void
    {
        $manifest = $this->manifest([
            'variable-groups' => [
                'app' => [
                    'APP_URL' => [],
                    'APP_ENV' => [],
                ],
            ],
            'phases' => [
                'build' => [
                    'app' => ['APP_URL'],
                ],
            ],
            'pipelines' => [
                'dev' => [],
            ],
        ]);

        $keys = $manifest->resolvePhaseKeys('dev', 'build');

        self::assertContains('APP_URL', $keys);
        self::assertNotContains('APP_ENV', $keys);
    }

    public function testReturnsEmptyKeysForKnownEmptyPhase(): void
    {
        $manifest = $this->manifest($this->manifestData());

        self::assertSame([], $manifest->resolvePhaseKeys('dev', 'setup'));
    }

    public function testReportsUnknownPhase(): void
    {
        $manifest = $this->manifest($this->manifestData());

        self::assertSame(['Unbekannte Phase: setvp'], $manifest->pipelinePhaseErrors('dev', 'setvp'));
    }

    public function testReportsUnknownPipeline(): void
    {
        $manifest = $this->manifest($this->manifestData());

        self::assertSame(['Unbekannte Pipeline: deev'], $manifest->pipelinePhaseErrors('deev', 'build'));
    }

    public function testDisjointPassesWhenNoOverlap(): void
    {
        $manifest = $this->manifest($this->manifestData());

        self::assertSame([], $manifest->checkDisjoint('dev', 'build'));
    }

    public function testDisjointFailsOnOverlap(): void
    {
        $manifest = $this->manifest([
            'variable-groups' => [
                'app' => [
                    'APP_URL' => [],
                    'APP_ENV' => [],
                ],
            ],
            'phases' => [
                'build' => [
                    'app' => ['APP_URL'],
                ],
            ],
            'pipelines' => [
                'dev' => [
                    'build' => [
                        'app' => ['APP_URL'],
                    ],
                ],
            ],
        ]);

        $errors = $manifest->checkDisjoint('dev', 'build');

        self::assertNotEmpty($errors);
        self::assertStringContainsString('APP_URL', $errors[0]);
    }

    public function testUnknownGroupFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannter group-key: missing');

        $manifest = $this->manifest([
            'variable-groups' => [
                'app' => [
                    'APP_URL' => [],
                ],
            ],
            'phases' => [
                'build' => [
                    'missing' => '*',
                ],
            ],
            'pipelines' => [
                'dev' => [],
            ],
        ]);

        $manifest->resolvePhaseKeys('dev', 'build');
    }

    public function testUnknownVariableFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannter Variablen-key APP_ENV in group-key: app');

        $manifest = $this->manifest([
            'variable-groups' => [
                'app' => [
                    'APP_URL' => [],
                ],
            ],
            'phases' => [
                'build' => [
                    'app' => ['APP_ENV'],
                ],
            ],
            'pipelines' => [
                'dev' => [],
            ],
        ]);

        $manifest->resolvePhaseKeys('dev', 'build');
    }

    private function manifest(array $data): Manifest
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $data);
        return new Manifest($root);
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
            'variable-groups' => [
                'app' => [
                    'APP_URL' => [],
                    'APP_ENV' => [],
                ],
            ],
            'phases' => [
                'setup' => [],
                'build' => [
                    'app' => '*',
                ],
            ],
            'pipelines' => [
                'dev' => [],
            ],
        ];
    }
}
