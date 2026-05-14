<?php

declare(strict_types=1);

namespace PipelineConfigSpec\Tests\Spec;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PipelineConfigSpec\Internal\ConfigPolicy;
use PipelineConfigSpec\Internal\ConfigSnapshot;
use PipelineConfigSpec\Internal\ManifestPipeline;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Spec: Validierungsregeln V-01 bis V-05
 * @see SPEC.md
 */
#[Group('spec')]
final class ValidationSpecTest extends TestCase
{
    /** @see SPEC.md V-01 */
    public function testV01_MissingRequiredVarFails(): void
    {
        $pipeline = new ManifestPipeline(['runtime' => ['APP_URL']], []);
        $snapshot = new ConfigSnapshot([], [], []);

        $errors = (new ConfigPolicy())->validateSnapshot($pipeline, $snapshot);

        self::assertContains('Fehlende Pflicht-Variable: APP_URL', $errors);
    }

    /** @see SPEC.md V-02 */
    public function testV02_EmptyValueFails(): void
    {
        $pipeline = new ManifestPipeline(['runtime' => ['APP_URL']], []);
        $snapshot = new ConfigSnapshot(['APP_URL' => ''], ['APP_URL' => 'file'], []);

        $errors = (new ConfigPolicy())->validateSnapshot($pipeline, $snapshot);

        self::assertContains('Leerer Wert nicht erlaubt: APP_URL', $errors);
    }

    /** @see SPEC.md V-03 */
    public function testV03_SourceViolationFails(): void
    {
        $pipeline = new ManifestPipeline(['runtime' => ['SECRET']], ['SECRET' => ['local']]);
        $snapshot = new ConfigSnapshot(['SECRET' => 'val'], ['SECRET' => 'cli'], []);

        $errors = (new ConfigPolicy())->validateSnapshot($pipeline, $snapshot);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('Variable in falscher Quelle: SECRET', $errors[0]);
    }

    /** @see SPEC.md V-03, R-05 — Quellen-Typ 'unknown' bei leerem source-Wert */
    public function testV03_UnknownSourceTypeFailsWhenPolicyIsSet(): void
    {
        $pipeline = new ManifestPipeline(['runtime' => ['SECRET']], ['SECRET' => ['local', 'cli']]);
        $snapshot = new ConfigSnapshot(['SECRET' => 'val'], ['SECRET' => ''], []);

        $errors = (new ConfigPolicy())->validateSnapshot($pipeline, $snapshot);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('Variable in falscher Quelle: SECRET', $errors[0]);
    }

    /** @see SPEC.md V-04 */
    public function testV04_SuperfluousVarFails(): void
    {
        $pipeline = new ManifestPipeline(['runtime' => ['APP_URL']], []);
        $snapshot = new ConfigSnapshot(
            ['APP_URL' => 'https://example.test', 'GHOST' => 'x'],
            ['APP_URL' => 'file', 'GHOST' => 'file'],
            []
        );

        $errors = (new ConfigPolicy())->validateSnapshot($pipeline, $snapshot);

        self::assertContains('Überflüssige Variable: GHOST', $errors);
    }

    /** @see SPEC.md V-05 */
    public function testV05_ManifestDefaultBypassesSourcePolicy(): void
    {
        $root = $this->createRoot();
        $this->writeManifest($root, [
            'variable-groups' => [
                'app' => [
                    'APP_URL'  => [],
                    'APP_PORT' => ['default' => '8080', 'sources' => ['local', 'cli']],
                ],
            ],
            'phases' => ['runtime' => ['app' => '*']],
            'pipelines' => ['dev' => []],
        ]);
        $this->writeYaml($root, Path::join('pipeline-config', 'dev.yaml'), Yaml::dump([
            'app' => ['APP_URL' => 'https://example.test'],
        ]));

        $snapshot = (new ConfigCompiler($root))->resolve('dev', 'runtime');

        self::assertSame('8080', $snapshot->values()['APP_PORT'] ?? null);
        self::assertSame('default', $snapshot->sources()['APP_PORT'] ?? null);
    }

    private function createRoot(): string
    {
        $root = Path::join(sys_get_temp_dir(), 'spec-validation-' . bin2hex(random_bytes(6)));
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
