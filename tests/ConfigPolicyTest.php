<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\ConfigPolicy;
use PipelineConfigSpec\Internal\ConfigSnapshot;
use PipelineConfigSpec\Internal\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class ConfigPolicyTest extends TestCase
{
    public function testValidConfigPasses(): void
    {
        $errors = $this->validateSnapshot('dev', 'runtime', [
            'APP_URL' => 'https://example.test',
        ]);

        self::assertSame([], $errors);
    }

    public function testUnexpectedKeyFails(): void
    {
        $errors = $this->validateSnapshot('dev', 'runtime', [
            'APP_URL' => 'https://example.test',
            'EXTRA' => 'x',
        ]);

        self::assertNotEmpty($errors);
        self::assertContains('Unexpected key: EXTRA', $errors);
    }

    public function testUnknownPipelineFails(): void
    {
        $errors = $this->validateSnapshot('deev', 'runtime', []);

        self::assertSame(['Unbekannte Pipeline: deev'], $errors);
    }

    public function testUnknownPhaseFails(): void
    {
        $errors = $this->validateSnapshot('dev', 'setvp', []);

        self::assertSame(['Unbekannte Phase: setvp'], $errors);
    }

    public function testSourceMismatchFails(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'mail' => [
                    'SMTP_PASS' => [
                        'sources' => ['local'],
                    ],
                ],
            ],
            'phases' => [
                'runtime' => [
                    'mail' => ['SMTP_PASS'],
                ],
            ],
            'pipelines' => [
                'dev' => [],
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

    public function testEmptyPhasePassesWhenSnapshotIsEmpty(): void
    {
        $errors = $this->validateSnapshot('dev', 'setup', []);

        self::assertSame([], $errors);
    }

    private function validateSnapshot(string $pipeline, string $phase, array $values): array
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $policy = new ConfigPolicy();
        $manifest = new Manifest($root);
        $origins = array_fill_keys(array_keys($values), 'file');
        $snapshot = new ConfigSnapshot($values, $origins, []);

        return $policy->validate($manifest, $pipeline, $phase, $snapshot);
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'config-pipeline-spec-' . uniqid('', true));
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        mkdir(Path::join($root, 'pipeline-config'), 0775, true);
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
                ],
            ],
            'phases' => [
                'setup' => [],
                'runtime' => [
                    'app' => '*',
                ],
            ],
            'pipelines' => [
                'dev' => [],
            ],
        ];
    }
}
