<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests\Spec;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Spec: Auflösungsregeln R-01 bis R-05, M-03
 * @see SPEC.md
 */
#[Group('spec')]
final class ResolutionSpecTest extends TestCase
{
    /** @see SPEC.md R-04 */
    public function testR04_MissingPipelineFileIsSkipped(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => ['app' => ['APP_URL' => []]],
            'phases' => ['runtime' => ['app' => '*']],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('.local', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://local.test'],
        ]));

        $snapshot = (new ConfigCompiler($root))->resolve('dev', 'runtime');

        self::assertSame('https://local.test', $snapshot->values()['APP_URL'] ?? null);
    }

    /** @see SPEC.md R-02, R-03 */
    public function testR02R03_LocalFileOverridesPipelineFile(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => ['app' => ['APP_URL' => []]],
            'phases' => ['runtime' => ['app' => '*']],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://file.test'],
        ]));
        $this->writeYaml($root, Path::join('.local', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://local.test'],
        ]));

        $snapshot = (new ConfigCompiler($root))->resolve('dev', 'runtime');

        self::assertSame('https://local.test', $snapshot->values()['APP_URL'] ?? null);
        self::assertStringContainsString('/.local/', $snapshot->sources()['APP_URL'] ?? '');
    }

    /** @see SPEC.md R-02, R-03 */
    public function testR02R03_CliOverridesLocalFile(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => ['app' => ['APP_URL' => []]],
            'phases' => ['runtime' => ['app' => '*']],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('.local', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://local.test'],
        ]));

        $snapshot = (new ConfigCompiler($root))->resolve('dev', 'runtime', ['APP_URL' => 'https://cli.test']);

        self::assertSame('https://cli.test', $snapshot->values()['APP_URL'] ?? null);
        self::assertSame('cli', $snapshot->sources()['APP_URL'] ?? null);
    }

    /** @see SPEC.md R-02 */
    public function testR02_ManifestDefaultHasLowestPriority(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => ['app' => ['APP_PORT' => ['default' => '8080']]],
            'phases' => ['runtime' => ['app' => '*']],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_PORT' => '9090'],
        ]));

        $snapshot = (new ConfigCompiler($root))->resolve('dev', 'runtime');

        self::assertSame('9090', $snapshot->values()['APP_PORT'] ?? null);
    }

    /** @see SPEC.md M-03 */
    public function testM03_PipelineSpecificPhaseAddsVarsAdditively(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'app'  => ['APP_URL' => []],
                'sftp' => ['SFTP_HOST' => []],
            ],
            'phases' => [
                'deploy' => ['app' => '*'],
            ],
            'pipelines' => [
                'dev' => ['deploy' => ['sftp' => '*']],
            ],
        ]);
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app'  => ['APP_URL' => 'https://example.test'],
            'sftp' => ['SFTP_HOST' => 'sftp.example.test'],
        ]));

        $snapshot = (new ConfigCompiler($root))->resolve('dev', 'deploy');

        self::assertSame('https://example.test', $snapshot->values()['APP_URL'] ?? null);
        self::assertSame('sftp.example.test', $snapshot->values()['SFTP_HOST'] ?? null);
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'spec-resolution-' . bin2hex(random_bytes(6)));
        mkdir($root, 0775, true);
        mkdir(Path::join($root, 'pipeline-config'), 0775, true);
        mkdir(Path::join($root, '.local'), 0775, true);
        return $root;
    }

    private function writeManifest(string $root, array $data): void
    {
        file_put_contents(
            Path::join($root, 'pipeline-config', 'manifest.yaml'),
            Yaml::dump($data, 8, 2)
        );
    }

    private function writeYaml(string $root, string $relPath, string $content): void
    {
        $path = Path::join($root, $relPath);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
    }
}
