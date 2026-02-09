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
        $phaseConfig = $manifest->resolvePhaseConfig('dev', 'build');
        $allowed = $manifest->expandAllowed($phaseConfig['allowed'] ?? []);

        self::assertContains('APP_URL', $allowed);
    }

    public function testRequiredFiltersWildcards(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $manifest = new Manifest($root);
        $phaseConfig = $manifest->resolvePhaseConfig('dev', 'runtime');
        $required = $manifest->expandRequired($phaseConfig['required'] ?? []);

        self::assertNotContains('APP_*', $required);
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
                    'build' => [
                        'required' => ['APP_URL'],
                        'allowed' => ['app'],
                    ],
                    'runtime' => [
                        'required' => ['APP_*'],
                        'allowed' => ['app'],
                    ],
                ],
                'dev' => [
                    'build' => [
                        'required' => [],
                        'allowed' => [],
                    ],
                ],
            ],
        ];
    }
}
