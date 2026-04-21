<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ConfigCompilerTest extends TestCase
{
    public function testCompileWritesFilteredConfig(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->seedYamlFiles($root);
        $compiled = $this->compilePayload($root);
        $values = $compiled['values'] ?? [];
        $pipelinePhase = $compiled['pipeline_phase'] ?? [];

        self::assertArrayNotHasKey('PIPELINE', $values);
        self::assertArrayNotHasKey('PHASE', $values);
        self::assertSame('https://example.test', $values['APP_URL'] ?? null);
        self::assertSame('dev', $pipelinePhase['pipeline'] ?? null);
        self::assertSame('runtime', $pipelinePhase['phase'] ?? null);
    }

    public function testCompileThrowsOnUnexpectedKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected key: EXTRA');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->seedYamlFiles($root);
        $this->writeYaml($root, 'config/runtime.yaml', "EXTRA: ignore\n");

        $this->compilePayload($root);
    }

    public function testCompileThrowsOnUnknownPipeline(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannte Pipeline: deev');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $compiler->compile('deev', 'runtime', $root . '/out/config.php');
    }

    public function testCompileThrowsOnUnknownPhase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannte Phase: setvp');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $compiler->compile('dev', 'setvp', $root . '/out/config.php');
    }

    public function testCompileReadsCliOverrideWhenSourceAllowsIt(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'security' => [
                    'IP_SALT' => [
                        'sources' => ['cli'],
                    ],
                ],
            ],
            'phases' => [
                'runtime' => [
                    'security' => ['IP_SALT'],
                ],
            ],
            'pipelines' => [
                'dev' => [],
            ],
        ]);

        $compiler = new ConfigCompiler($root);
        $targetPath = $root . '/out/config.php';
        $path = $compiler->compile('dev', 'runtime', $targetPath, [
            'runtime.security.IP_SALT' => 'test-salt',
        ]);
        $compiled = $this->readConfig($path);
        $values = $compiled['values'] ?? [];

        self::assertSame('test-salt', $values['IP_SALT'] ?? null);
    }

    public function testCompileAllowsKnownEmptyPhase(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $targetPath = $root . '/out/config.php';
        $path = $compiler->compile('dev', 'setup', $targetPath);
        $compiled = $this->readConfig($path);
        $values = $compiled['values'] ?? [];
        $pipelinePhase = $compiled['pipeline_phase'] ?? [];

        self::assertSame([], $values);
        self::assertSame('dev', $pipelinePhase['pipeline'] ?? null);
        self::assertSame('setup', $pipelinePhase['phase'] ?? null);
    }

    public function testCompileRejectsValuesInEmptyPhase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected key: APP_URL');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->writeYaml($root, 'config/dev-setup.yaml', "APP_URL: https://example.test\n");

        $compiler = new ConfigCompiler($root);
        $compiler->compile('dev', 'setup', $root . '/out/config.php');
    }

    public function testCompileRejectsOverrideThatIsNotVariableOfPipelinePhase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected key: PIPELINE');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $compiler->compile('dev', 'runtime', $root . '/out/config.php', [
            'runtime.app.PIPELINE' => 'prod',
        ]);
    }

    public function testCompileRejectsPhaseOverrideThatIsNotVariableOfPipelinePhase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected key: PHASE');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $compiler->compile('dev', 'runtime', $root . '/out/config.php', [
            'runtime.app.PHASE' => 'build',
        ]);
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

    private function writeYaml(string $root, string $file, string $content): void
    {
        $path = $root . '/' . $file;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Failed to write yaml file.');
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

    private function seedYamlFiles(string $root): void
    {
        $this->writeYaml($root, 'config/dev-runtime.yaml', "APP_URL: https://example.test\n");
    }

    private function compilePayload(string $root): array
    {
        $compiler = new ConfigCompiler($root);
        $targetPath = $root . '/out/config.php';
        $path = $compiler->compile('dev', 'runtime', $targetPath);
        return $this->readConfig($path);
    }

    private function readConfig(string $path): array
    {
        $values = require $path;
        return is_array($values) ? $values : [];
    }

}
