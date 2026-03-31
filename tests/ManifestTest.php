<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ManifestTest extends TestCase
{
    public function testExpandsGroups(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $manifest = new Manifest($root);
        $keys = $manifest->resolvePhaseKeys('dev', 'build');

        self::assertContains('APP_URL', $keys);
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
            'variables' => [
                'app' => [
                    'APP_URL' => [],
                    'APP_ENV' => [],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'build' => ['APP_URL'],
                ],
                'dev' => [
                    'build' => ['APP_URL'],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $errors = $manifest->checkDisjoint('dev', 'build');

        self::assertNotEmpty($errors);
        self::assertStringContainsString('APP_URL', $errors[0]);
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
                'app' => [
                    'APP_URL' => [],
                    'APP_ENV' => [],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'build' => ['app'],
                ],
                'dev' => [
                    'build' => [],
                ],
            ],
        ];
    }
}
