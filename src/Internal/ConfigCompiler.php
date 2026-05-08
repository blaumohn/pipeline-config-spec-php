<?php

namespace PipelineConfigSpec\Internal;

use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class ConfigCompiler
{
    private string $rootPath;
    private string $configDir;
    private ConfigLoader $loader;
    private Manifest $manifest;
    private ManifestValidator $manifestValidator;
    private ConfigPolicy $policy;

    public function __construct(string $rootPath, string $configDir = 'config')
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->configDir = $this->normalizeConfigDir($configDir);
        $this->loader = new ConfigLoader($this->rootPath, $this->configDir);
        $this->manifest = new Manifest($this->rootPath, $this->configDir);
        $this->manifestValidator = new ManifestValidator();
        $this->policy = new ConfigPolicy();
    }

    public function compile(
        string $pipeline,
        string $phase,
        ?string $targetPath = null,
        array $overrides = []
    ): string {
        $snapshot = $this->resolve($pipeline, $phase, $overrides);
        $targetPath = $this->resolveTargetPath($targetPath);
        $values = $this->filterCompiledValues($pipeline, $phase, $snapshot->values());
        $payload = $this->compiledPayload($pipeline, $phase, $values);
        $this->writeCompiled($targetPath, $payload);

        return $targetPath;
    }

    public function resolve(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        $snapshot = $this->buildSnapshot($pipeline, $phase, $overrides);
        $this->assertValidSnapshot($pipeline, $phase, $snapshot);
        return $snapshot;
    }

    public function validate(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        return $this->resolve($pipeline, $phase, $overrides);
    }

    public function cliVarsForPhase(string $pipeline, string $phase): array
    {
        $keys = $this->manifest->resolvePhaseKeys($pipeline, $phase);
        return array_values(array_filter($keys, $this->isCliVar(...)));
    }

    private function isCliVar(string $key): bool
    {
        $policy = $this->manifest->sourcePolicyForVariable($key);
        return $policy === [] || in_array('cli', $policy, true);
    }

    private function assertValidPipelinePhase(string $pipeline, string $phase): void
    {
        $errors = $this->manifest->pipelinePhaseErrors($pipeline, $phase);
        if ($errors === []) {
            return;
        }
        throw new \RuntimeException("Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
    }

    private function buildSnapshot(
        string $pipeline,
        string $phase,
        array $overrides
    ): ConfigSnapshot {
        $this->manifestValidator->validate($this->manifest->data());
        $this->assertValidPipelinePhase($pipeline, $phase);

        $fileLayer = $this->loader->load($pipeline, $phase);
        $cliLayer = $this->loader->loadOverrides($pipeline, $phase, $overrides);

        return $this->mergeLayers([$fileLayer, $cliLayer]);
    }

    private function mergeLayers(array $layers): ConfigSnapshot
    {
        $values = [];
        $sources = [];
        $loadedFiles = [];
        foreach ($layers as $layer) {
            if (!$layer instanceof ConfigSnapshot) {
                continue;
            }
            $values = array_merge($values, $layer->values());
            $sources = array_merge($sources, $layer->sources());
            $loadedFiles = array_merge($loadedFiles, $layer->loadedFiles());
        }
        return new ConfigSnapshot($values, $sources, $loadedFiles);
    }

    private function assertValidSnapshot(
        string $pipeline,
        string $phase,
        ConfigSnapshot $snapshot
    ): void {
        $errors = $this->policy->validate($this->manifest, $pipeline, $phase, $snapshot);
        if ($errors === []) {
            return;
        }

        $message = "Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors);
        throw new \RuntimeException($message);
    }

    private function filterCompiledValues(string $pipeline, string $phase, array $values): array
    {
        $phaseKeys = $this->manifest->resolvePhaseKeys($pipeline, $phase);
        $expectedKeys = array_flip($phaseKeys);
        $filtered = [];
        foreach ($values as $key => $value) {
            if (isset($expectedKeys[$key])) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    private function compiledPayload(string $pipeline, string $phase, array $values): array
    {
        return [
            'pipeline_phase' => [
                'pipeline' => $pipeline,
                'phase' => $phase,
            ],
            'values' => $values,
        ];
    }

    private function resolveTargetPath(?string $targetPath): string
    {
        if ($targetPath !== null) {
            return $targetPath;
        }
        return Path::join($this->rootPath, 'var', 'config', 'config.php');
    }

    private function writeCompiled(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
        file_put_contents($path, $content);
    }

    private function normalizeConfigDir(string $configDir): string
    {
        $trimmed = trim($configDir, DIRECTORY_SEPARATOR);
        if ($trimmed === '') {
            return 'config';
        }
        return $trimmed;
    }
}
