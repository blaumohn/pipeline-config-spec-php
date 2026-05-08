<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\PipelineConfigService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

final class PreviewResolveMatrixTest extends TestCase
{
    public function testPreviewDeployUsesLocalCredentialsWhenSystemLayerIsEmpty(): void
    {
        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $this->writeLocalConfig($root);
        $service = new PipelineConfigService($root);

        $report = $service->describe('preview', 'deploy');

        self::assertSame('local-host', $report['values']['SFTP_HOST'] ?? null);
        self::assertSame('local-user', $report['values']['SFTP_USER'] ?? null);
        self::assertSame('local-pass', $report['values']['SFTP_PASS'] ?? null);
        self::assertSame('22', $report['values']['SFTP_PORT'] ?? null);
        self::assertStringContainsString(
            '/.local/pipeline-config.yaml',
            $report['sources']['SFTP_HOST'] ?? ''
        );
        self::assertSame('default', $report['sources']['SFTP_PORT'] ?? null);
    }

    public function testPreviewDeployUsesCliOverrideBeforeLocal(): void
    {
        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $this->writeLocalConfig($root);
        $service = new PipelineConfigService($root);

        $report = $service->describe('preview', 'deploy', ['SFTP_HOST' => 'override-host']);

        self::assertSame('override-host', $report['values']['SFTP_HOST'] ?? null);
        self::assertSame('cli', $report['sources']['SFTP_HOST'] ?? null);
    }

    public function testPreviewDeployFailsWhenCredentialIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required key: SFTP_HOST');

        $root = $this->createRoot();
        $this->writePreviewConfig($root, includeCredentials: false);
        $service = new PipelineConfigService($root);

        $service->values('preview', 'deploy');
    }

    public function testPreviewDeployRejectsCliOverrideWhenManifestForbidsIt(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Variable in falscher Quelle: SFTP_HOST');

        $root = $this->createRoot();
        $this->writePreviewConfig($root, allowCliCredentials: false);
        $this->writeLocalConfig($root);
        $service = new PipelineConfigService($root);

        $service->values('preview', 'deploy', ['SFTP_HOST' => 'override-host']);
    }

    public function testPreviewRuntimeCompileUsesCliSecretAndKeepsPhase(): void
    {
        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $service = new PipelineConfigService($root);

        $path = $service->compile('preview', 'runtime', Path::join($root, 'out', 'runtime.php'), [
            'SMTP_PASS' => 'runtime-pass',
        ]);
        $compiled = require $path;

        self::assertSame('preview', $compiled['pipeline_phase']['pipeline'] ?? null);
        self::assertSame('runtime', $compiled['pipeline_phase']['phase'] ?? null);
        self::assertSame('runtime-pass', $compiled['values']['SMTP_PASS'] ?? null);
        self::assertSame('kontakt@example.test', $compiled['values']['SMTP_FROM_EMAIL'] ?? null);
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'config-preview-' . bin2hex(random_bytes(6)));
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        mkdir(Path::join($root, 'pipeline-config'), 0775, true);
        mkdir(Path::join($root, '.local'), 0775, true);
        return $root;
    }

    private function writePreviewConfig(
        string $root,
        bool $includeCredentials = true,
        bool $allowCliCredentials = true
    ): void {
        $sftpHostSources = $allowCliCredentials ? ['local', 'cli'] : ['local'];
        $manifest = [
            'variable-groups' => [
                'smtp' => [
                    'SMTP_PASS' => ['sources' => ['local', 'cli']],
                    'SMTP_FROM_EMAIL' => [],
                    'SMTP_FROM_NAME' => [],
                ],
                'sftp' => [
                    'SFTP_SERVER_DIR' => [],
                    'SFTP_PORT' => ['default' => '22'],
                    'SFTP_HOST' => ['sources' => $sftpHostSources],
                    'SFTP_USER' => ['sources' => $sftpHostSources],
                    'SFTP_PASS' => ['sources' => $sftpHostSources],
                ],
            ],
            'phases' => [
                'runtime' => [],
                'deploy' => [],
            ],
            'pipelines' => [
                'preview' => [
                    'runtime' => ['smtp' => '*'],
                    'deploy' => ['sftp' => '*'],
                ],
            ],
        ];
        $this->writeYaml($root, Path::join('pipeline-config', 'manifest.yaml'), Yaml::dump($manifest, 8, 2));

        $pipelineConfig = [
            'smtp' => [
                'SMTP_FROM_EMAIL' => 'kontakt@example.test',
                'SMTP_FROM_NAME' => 'Preview',
            ],
            'sftp' => [
                'SFTP_SERVER_DIR' => '/home/preview/public',
            ],
        ];
        if ($includeCredentials) {
            $pipelineConfig['sftp']['SFTP_HOST'] = 'file-host';
            $pipelineConfig['sftp']['SFTP_USER'] = 'file-user';
            $pipelineConfig['sftp']['SFTP_PASS'] = 'file-pass';
        }
        $this->writeYaml($root, Path::join('pipeline-config', 'preview.yaml'), Yaml::dump($pipelineConfig, 8, 2));
    }

    private function writeLocalConfig(string $root): void
    {
        $payload = [
            'sftp' => [
                'SFTP_HOST' => 'local-host',
                'SFTP_USER' => 'local-user',
                'SFTP_PASS' => 'local-pass',
            ],
        ];
        $this->writeYaml($root, Path::join('.local', 'pipeline-config.yaml'), Yaml::dump($payload, 8, 2));
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
}
