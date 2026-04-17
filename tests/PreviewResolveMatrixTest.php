<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests;

use PipelineConfigSpec\PipelineConfigService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class PreviewResolveMatrixTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->clearPreviewEnv();
    }

    public function testPreviewDeployUsesLocalCredentialsWhenSystemLayerIsEmpty(): void
    {
        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $this->writeLocalConfig($root);
        $service = new PipelineConfigService($root);

        $report = $service->describe('preview', 'deploy');

        self::assertSame('local-host', $report['values']['FTP_HOST'] ?? null);
        self::assertSame('local-user', $report['values']['FTP_USER'] ?? null);
        self::assertSame('local-pass', $report['values']['FTP_PASS'] ?? null);
        self::assertSame(2121, $report['values']['FTP_PORT'] ?? null);
        self::assertStringContainsString(
            '/.local/preview-deploy.yaml',
            $report['sources']['FTP_HOST'] ?? ''
        );
        self::assertStringContainsString(
            '/config/preview-deploy.yaml',
            $report['sources']['FTP_PORT'] ?? ''
        );
    }

    public function testPreviewDeployUsesSystemLayerBeforeLocal(): void
    {
        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $this->writeLocalConfig($root);
        $this->setDeployEnv();
        $service = new PipelineConfigService($root);

        $systemValues = $service->describe('preview', 'deploy');
        self::assertSame('system-host', $systemValues['values']['FTP_HOST'] ?? null);
        self::assertSame('system', $systemValues['sources']['FTP_HOST'] ?? null);
    }

    public function testPreviewDeployFailsWhenCredentialIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required key: FTP_HOST');

        $root = $this->createRoot();
        $this->writePreviewConfig($root, includeCredentials: false);
        $service = new PipelineConfigService($root);

        $service->values('preview', 'deploy');
    }

    public function testPreviewDeployRejectsSystemSourceWhenManifestForbidsIt(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Variable in falscher Quelle: FTP_HOST');

        $root = $this->createRoot();
        $this->writePreviewConfig($root, allowSystemCredentials: false);
        $this->setDeployEnv();
        $service = new PipelineConfigService($root);

        $service->values('preview', 'deploy');
    }

    public function testPreviewDeployRejectsCliOverrideWhenManifestForbidsIt(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Variable in falscher Quelle: FTP_HOST');

        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $this->writeLocalConfig($root);
        $service = new PipelineConfigService($root);

        $service->values('preview', 'deploy', [
            'FTP_HOST' => 'cli-host',
        ]);
    }

    public function testPreviewRuntimeCompileUsesSystemSecretAndKeepsPhase(): void
    {
        $root = $this->createRoot();
        $this->writePreviewConfig($root);
        $this->setRuntimeEnv();
        $service = new PipelineConfigService($root);

        $path = $service->compile('preview', 'runtime', $root . '/out/runtime.php');
        $compiled = require $path;

        self::assertSame('preview', $compiled['pipeline_phase']['pipeline'] ?? null);
        self::assertSame('runtime', $compiled['pipeline_phase']['phase'] ?? null);
        self::assertSame('runtime-pass', $compiled['values']['SMTP_PASS'] ?? null);
        self::assertSame('kontakt@example.test', $compiled['values']['SMTP_FROM_EMAIL'] ?? null);
    }

    private function createRoot(): string
    {
        $root = '/tmp/config-preview-' . bin2hex(random_bytes(6));
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create root directory.');
        }
        if (!mkdir($root . '/config', 0775, true) && !is_dir($root . '/config')) {
            throw new \RuntimeException('Failed to create config directory.');
        }
        if (!mkdir($root . '/.local', 0775, true) && !is_dir($root . '/.local')) {
            throw new \RuntimeException('Failed to create local directory.');
        }
        return $root;
    }

    private function writePreviewConfig(
        string $root,
        bool $includeCredentials = true,
        bool $allowSystemCredentials = true
    ): void {
        $manifest = [
            'variable-groups' => [
                'smtp' => [
                    'SMTP_PASS' => [
                        'sources' => ['system', 'local'],
                    ],
                    'SMTP_FROM_EMAIL' => [],
                    'SMTP_FROM_NAME' => [],
                ],
                'ftp' => [
                    'FTP_SERVER_DIR' => [],
                    'FTP_PORT' => [],
                    'FTP_HOST' => $this->sourceRule($allowSystemCredentials),
                    'FTP_USER' => $this->sourceRule($allowSystemCredentials),
                    'FTP_PASS' => $this->sourceRule($allowSystemCredentials),
                ],
            ],
            'phases' => [
                'runtime' => [],
                'deploy' => [],
            ],
            'pipelines' => [
                'preview' => [
                    'runtime' => [
                        'smtp' => '*',
                    ],
                    'deploy' => [
                        'ftp' => '*',
                    ],
                ],
            ],
        ];
        $this->writeYaml($root, 'config/config.manifest.yaml', Yaml::dump($manifest, 8, 2));

        $deploy = [
            'deploy' => [
                'FTP_SERVER_DIR' => '/home/preview/public',
                'FTP_PORT' => 2121,
            ],
        ];
        if ($includeCredentials) {
            $deploy['deploy']['FTP_HOST'] = 'file-host';
            $deploy['deploy']['FTP_USER'] = 'file-user';
            $deploy['deploy']['FTP_PASS'] = 'file-pass';
        }
        $this->writeYaml($root, 'config/preview-deploy.yaml', Yaml::dump($deploy, 8, 2));

        $runtime = [
            'runtime' => [
                'SMTP_FROM_EMAIL' => 'kontakt@example.test',
                'SMTP_FROM_NAME' => 'Preview',
            ],
        ];
        $this->writeYaml($root, 'config/preview-runtime.yaml', Yaml::dump($runtime, 8, 2));
    }

    private function writeLocalConfig(string $root): void
    {
        $payload = [
            'deploy' => [
                'FTP_HOST' => 'local-host',
                'FTP_USER' => 'local-user',
                'FTP_PASS' => 'local-pass',
            ],
        ];
        $this->writeYaml($root, '.local/preview-deploy.yaml', Yaml::dump($payload, 8, 2));
    }

    private function sourceRule(bool $allowSystemCredentials): array
    {
        if ($allowSystemCredentials) {
            return ['sources' => ['system', 'local']];
        }
        return ['sources' => ['local']];
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

    private function setDeployEnv(): void
    {
        putenv('FTP_HOST=system-host');
        putenv('FTP_USER=system-user');
        putenv('FTP_PASS=system-pass');
    }

    private function setRuntimeEnv(): void
    {
        putenv('SMTP_PASS=runtime-pass');
    }

    private function clearPreviewEnv(): void
    {
        putenv('SMTP_PASS');
        putenv('FTP_HOST');
        putenv('FTP_USER');
        putenv('FTP_PASS');
    }
}
