<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests\Spec;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PipelineConfigSpec\Internal\Manifest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Spec: Manifest-Regeln M-01 bis M-05
 * @see SPEC.md
 */
#[Group('spec')]
final class ManifestSpecTest extends TestCase
{
    /** @see SPEC.md M-04 */
    public function testM04_DisjointnessViolationFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Disjunktheitsverletzung/');

        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => ['app' => ['APP_URL' => []]],
            'phases' => ['build' => ['app' => ['APP_URL']]],
            'pipelines' => ['dev' => ['build' => ['app' => ['APP_URL']]]],
        ]);
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://example.test'],
        ]));

        (new ConfigCompiler($root))->resolve('dev', 'build');
    }

    /** @see SPEC.md M-05 */
    public function testM05_PipelineNamedCommonForbidden(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('common ist keine Pipeline im Manifest.');

        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => ['app' => ['APP_URL' => []]],
            'phases' => ['runtime' => ['app' => '*']],
            'pipelines' => ['common' => []],
        ]);

        new Manifest($root);
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'spec-manifest-' . bin2hex(random_bytes(6)));
        mkdir($root, 0775, true);
        mkdir(Path::join($root, 'pipeline-config'), 0775, true);
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
