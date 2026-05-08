<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class ConfigCompilerTest extends TestCase
{
    public function testCompileWritesFilteredConfig(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->seedYamlFiles($root);
        $compiled = $this->compilePayload($root);

        self::assertSame('https://example.test', $compiled['values']['APP_URL'] ?? null);
        self::assertSame('dev', $compiled['pipeline_phase']['pipeline'] ?? null);
        self::assertSame('runtime', $compiled['pipeline_phase']['phase'] ?? null);
    }

    public function testCompileIgnoresVarsFromOtherPhases(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://example.test'],
            'sftp' => ['SFTP_HOST' => 'sftp.example.test'],
        ]));

        $compiled = $this->compilePayload($root);

        self::assertSame('https://example.test', $compiled['values']['APP_URL'] ?? null);
        self::assertArrayNotHasKey('SFTP_HOST', $compiled['values'] ?? []);
    }

    public function testCompileAppliesManifestDefault(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'app' => [
                    'APP_URL' => [],
                    'APP_PORT' => ['default' => '8080'],
                ],
            ],
            'phases' => [
                'runtime' => ['app' => '*'],
            ],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://example.test'],
        ]));

        $compiled = $this->compilePayload($root);

        self::assertSame('8080', $compiled['values']['APP_PORT'] ?? null);
    }

    public function testCompileThrowsOnUnknownPipeline(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannte Pipeline: deev');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $compiler->compile('deev', 'runtime', Path::join($root, 'out', 'config.php'));
    }

    public function testCompileThrowsOnUnknownPhase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unbekannte Phase: setvp');

        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $compiler->compile('dev', 'setvp', Path::join($root, 'out', 'config.php'));
    }

    public function testCompileReadsCliOverrideWhenSourceAllowsIt(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'security' => [
                    'IP_SALT' => ['sources' => ['cli']],
                ],
            ],
            'phases' => [
                'runtime' => ['security' => ['IP_SALT']],
            ],
            'pipelines' => ['dev' => []],
        ]);

        $compiler = new ConfigCompiler($root);
        $path = $compiler->compile('dev', 'runtime', Path::join($root, 'out', 'config.php'), [
            'IP_SALT' => 'test-salt',
        ]);
        $compiled = $this->readConfig($path);

        self::assertSame('test-salt', $compiled['values']['IP_SALT'] ?? null);
    }

    public function testCompileRejectsCliOverrideWhenSourceForbidsIt(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Variable in falscher Quelle: SECRET');

        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'security' => [
                    'SECRET' => ['sources' => ['local']],
                ],
            ],
            'phases' => [
                'runtime' => ['security' => ['SECRET']],
            ],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('.local', 'pipeline-config.yaml'), Yaml::dump([
            'security' => ['SECRET' => 'local-secret'],
        ]));

        $compiler = new ConfigCompiler($root);
        $compiler->compile('dev', 'runtime', Path::join($root, 'out', 'config.php'), [
            'SECRET' => 'cli-secret',
        ]);
    }

    public function testCompileAllowsKnownEmptyPhase(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, $this->manifestData());

        $compiler = new ConfigCompiler($root);
        $path = $compiler->compile('dev', 'setup', Path::join($root, 'out', 'config.php'));
        $compiled = $this->readConfig($path);

        self::assertSame([], $compiled['values'] ?? []);
        self::assertSame('dev', $compiled['pipeline_phase']['pipeline'] ?? null);
        self::assertSame('setup', $compiled['pipeline_phase']['phase'] ?? null);
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'config-pipeline-spec-' . uniqid('', true));
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        mkdir(Path::join($root, 'pipeline-config'), 0775, true);
        mkdir(Path::join($root, '.local'), 0775, true);
        return $root;
    }

    private function writeManifest(string $root, array $manifest): void
    {
        $path = Path::join($root, 'pipeline-config', 'manifest.yaml');
        file_put_contents($path, Yaml::dump($manifest, 8, 2));
    }

    private function writeYaml(string $root, string $file, string $content): void
    {
        $path = Path::join($root, $file);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $content);
    }

    private function manifestData(): array
    {
        return [
            'variable-groups' => [
                'app' => ['APP_URL' => []],
                'sftp' => ['SFTP_HOST' => []],
            ],
            'phases' => [
                'setup' => [],
                'runtime' => ['app' => '*'],
                'deploy' => ['sftp' => '*'],
            ],
            'pipelines' => ['dev' => []],
        ];
    }

    private function seedYamlFiles(string $root): void
    {
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://example.test'],
        ]));
    }

    private function compilePayload(string $root): array
    {
        $compiler = new ConfigCompiler($root);
        $path = $compiler->compile('dev', 'runtime', Path::join($root, 'out', 'config.php'));
        return $this->readConfig($path);
    }

    private function readConfig(string $path): array
    {
        $values = require $path;
        return is_array($values) ? $values : [];
    }
}
