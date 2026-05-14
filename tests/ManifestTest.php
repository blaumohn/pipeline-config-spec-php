<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class ManifestTest extends TestCase
{
    public function testExpandsSelectAllGroup(): void
    {
        $manifest = $this->manifest($this->manifestData());
        $keys = $manifest->resolvePhaseVars('dev', 'build');

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

        $keys = $manifest->resolvePhaseVars('dev', 'build');

        self::assertContains('APP_URL', $keys);
        self::assertNotContains('APP_ENV', $keys);
    }

    public function testReturnsEmptyKeysForKnownEmptyPhase(): void
    {
        $manifest = $this->manifest($this->manifestData());

        self::assertSame([], $manifest->resolvePhaseVars('dev', 'setup'));
    }

    public function testReportsUnknownPhase(): void
    {
        $manifest = $this->manifest($this->manifestData());

        self::assertSame(['Unbekannte Phase: setvp'], $manifest->pipelinePhaseErrors('dev', 'setvp'));
    }

    public function testCommonPhaseIsValidWithoutExplicitPipelineListing(): void
    {
        $manifest = $this->manifest([
            'variable-groups' => [
                'app' => ['APP_URL' => []],
            ],
            'phases' => [
                'setup' => [],
            ],
            'pipelines' => [
                'dev' => [],   // kein setup-Eintrag unter pipelines.dev
            ],
        ]);

        self::assertSame([], $manifest->pipelinePhaseErrors('dev', 'setup'));
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

        $manifest->resolvePhaseVars('dev', 'build');
    }

    public function testDefaultValuesAreResolved(): void
    {
        $manifest = $this->manifest([
            'variable-groups' => [
                'sftp' => [
                    'SFTP_PORT' => ['default' => '22'],
                    'SFTP_HOST' => [],
                ],
            ],
            'phases' => [
                'deploy' => ['sftp' => '*'],
            ],
            'pipelines' => [
                'preview' => [],
            ],
        ]);

        $defaults = $manifest->defaultValues();

        self::assertSame('22', $defaults['SFTP_PORT'] ?? null);
        self::assertArrayNotHasKey('SFTP_HOST', $defaults);
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

        $manifest->resolvePhaseVars('dev', 'build');
    }

    private function manifest(array $data): Manifest
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $data);
        return new Manifest($root);
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
        return $root;
    }

    private function writeManifest(string $root, array $manifest): void
    {
        $payload = Yaml::dump($manifest, 8, 2);
        $path = Path::join($root, 'pipeline-config', 'manifest.yaml');
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
