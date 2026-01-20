<?php

declare(strict_types=1);

namespace ConfigPipelineSpec\Tests;

use ConfigPipelineSpec\Config\Context;
use ConfigPipelineSpec\Config\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ManifestTest extends TestCase
{
    public function testResolvesCommonAndPipelinePhase(): void
    {
        $root = $this->createFixture([
            'pipelines' => [
                'common' => [
                    'build' => [
                        'required' => ['PIPELINE', 'PHASE'],
                        'allowed' => ['context', 'captcha'],
                    ],
                ],
                'dev' => [
                    'build' => [
                        'required' => ['PROFILE'],
                        'allowed' => ['APP_URL'],
                    ],
                ],
            ],
            'variables' => [
                'context' => [
                    'PIPELINE' => [],
                    'PHASE' => [],
                    'PROFILE' => [],
                ],
                'captcha' => [
                    'CAPTCHA_MAX_GET' => [],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $context = new Context('dev', 'build', 'dev');
        $phase = $manifest->resolvePhaseConfig($context);

        self::assertNotNull($phase);
        $required = $manifest->expandRequired($phase['required'] ?? []);
        $allowed = $manifest->expandAllowed($phase['allowed'] ?? []);

        self::assertSame(['PIPELINE', 'PHASE', 'PROFILE'], $required);
        self::assertContains('CAPTCHA_MAX_GET', $allowed);
        self::assertContains('APP_URL', $allowed);
    }

    public function testExpandRequiredDropsWildcards(): void
    {
        $root = $this->createFixture([
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        'required' => ['APP_*', 'APP_URL'],
                        'allowed' => ['app'],
                    ],
                ],
            ],
            'variables' => [
                'app' => [
                    'APP_URL' => [],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $context = new Context('dev', 'runtime', null);
        $phase = $manifest->resolvePhaseConfig($context);

        self::assertNotNull($phase);
        $required = $manifest->expandRequired($phase['required'] ?? []);

        self::assertSame(['APP_URL'], $required);
    }

    private function createFixture(array $manifest): string
    {
        $root = sys_get_temp_dir() . '/env-pipeline-spec-' . uniqid('', true);
        $configDir = $root . '/config';
        if (!mkdir($configDir, 0775, true) && !is_dir($configDir)) {
            throw new \RuntimeException('Failed to create fixture directory.');
        }
        $payload = Yaml::dump($manifest, 8, 2);
        $path = $configDir . '/env.manifest.yaml';
        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Failed to write manifest.');
        }
        return $root;
    }
}
