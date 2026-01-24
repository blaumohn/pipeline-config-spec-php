<?php

declare(strict_types=1);

namespace ConfigPipelineSpec\Tests;

use ConfigPipelineSpec\Config\Context;
use ConfigPipelineSpec\Config\ConfigPolicy;
use ConfigPipelineSpec\Config\ConfigSnapshot;
use ConfigPipelineSpec\Config\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ConfigPolicyTest extends TestCase
{
    public function testRejectsDisallowedSource(): void
    {
        $root = $this->createFixture([
            'variables' => [
                'context' => [
                    'PIPELINE' => [],
                    'PHASE' => [],
                ],
                'security' => [
                    'IP_SALT' => ['sources' => ['system', 'local']],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        'required' => ['PIPELINE', 'PHASE', 'IP_SALT'],
                        'allowed' => ['context', 'security'],
                    ],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $policy = new ConfigPolicy();
        $context = new Context('dev', 'runtime');
        $snapshot = new ConfigSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
            'IP_SALT' => 'secret',
        ], [
            'PIPELINE' => 'system',
            'PHASE' => 'system',
            'IP_SALT' => '/tmp/config/dev-runtime.yaml',
        ], []);

        $errors = $policy->validate($manifest, $context, $snapshot);

        self::assertNotEmpty($errors);
    }

    public function testAllowsLocalSource(): void
    {
        $root = $this->createFixture([
            'variables' => [
                'context' => [
                    'PIPELINE' => [],
                    'PHASE' => [],
                ],
                'security' => [
                    'IP_SALT' => ['sources' => ['system', 'local']],
                ],
            ],
            'pipelines' => [
                'common' => [
                    'runtime' => [
                        'required' => ['PIPELINE', 'PHASE', 'IP_SALT'],
                        'allowed' => ['context', 'security'],
                    ],
                ],
            ],
        ]);

        $manifest = new Manifest($root);
        $policy = new ConfigPolicy();
        $context = new Context('dev', 'runtime');
        $snapshot = new ConfigSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
            'IP_SALT' => 'secret',
        ], [
            'PIPELINE' => 'system',
            'PHASE' => 'system',
            'IP_SALT' => '/tmp/.local/dev-runtime.yaml',
        ], []);

        $errors = $policy->validate($manifest, $context, $snapshot);

        self::assertSame([], $errors);
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
