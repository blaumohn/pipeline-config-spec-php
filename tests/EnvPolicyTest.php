<?php

declare(strict_types=1);

namespace EnvPipelineSpec\Tests;

use EnvPipelineSpec\Env\Context;
use EnvPipelineSpec\Env\EnvPolicy;
use EnvPipelineSpec\Env\EnvSnapshot;
use EnvPipelineSpec\Env\Manifest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class EnvPolicyTest extends TestCase
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
        $policy = new EnvPolicy();
        $context = new Context('dev', 'runtime', null);
        $snapshot = new EnvSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
            'IP_SALT' => 'secret',
        ], [
            'PIPELINE' => 'system',
            'PHASE' => 'system',
            'IP_SALT' => '/tmp/.env',
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
        $policy = new EnvPolicy();
        $context = new Context('dev', 'runtime', null);
        $snapshot = new EnvSnapshot([
            'PIPELINE' => 'dev',
            'PHASE' => 'runtime',
            'IP_SALT' => 'secret',
        ], [
            'PIPELINE' => 'system',
            'PHASE' => 'system',
            'IP_SALT' => '/tmp/.env.local',
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
