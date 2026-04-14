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
        $values = $this->filterCompiledValues($pipeline, $phase, $snapshot->values());

        $targetPath = $this->resolveTargetPath($targetPath);
        $this->writeCompiled($targetPath, $values);

        return $targetPath;
    }

    public function resolve(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        $snapshot = $this->buildSnapshot($pipeline, $phase, $overrides);
        $snapshot = $this->filterSnapshot($pipeline, $phase, $snapshot);
        $this->assertValidSnapshot($pipeline, $phase, $snapshot);
        return $snapshot;
    }

    public function validate(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        return $this->resolve($pipeline, $phase, $overrides);
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

        $systemKeys = $this->manifest->variableKeys();
        $fileLayer = $this->loader->load($pipeline, $phase);
        $systemLayer = $this->loader->loadSystem($systemKeys);
        $cliLayer = $this->loader->loadOverrides($overrides);

        return $this->mergeLayers([$fileLayer, $systemLayer, $cliLayer]);
    }

    private function mergeLayers(array $layers): ConfigSnapshot
    {
        $values = [];
        $origins = [];
        $loadedFiles = [];
        foreach ($layers as $layer) {
            if (!$layer instanceof ConfigSnapshot) {
                continue;
            }
            $values = array_merge($values, $layer->values());
            $origins = array_merge($origins, $layer->origins());
            $loadedFiles = array_merge($loadedFiles, $layer->loadedFiles());
        }
        return new ConfigSnapshot($values, $origins, $loadedFiles);
    }

    private function filterSnapshot(
        string $pipeline,
        string $phase,
        ConfigSnapshot $snapshot
    ): ConfigSnapshot {
        $phaseKeys = $this->manifest->resolvePhaseKeys($pipeline, $phase);

        $expectedKeys = array_flip(array_merge($phaseKeys, $this->manifest->variableKeys()));
        $values = [];
        $origins = [];
        foreach ($snapshot->values() as $key => $value) {
            $origin = $snapshot->origins()[$key] ?? '';
            if ($this->shouldKeep($key, $origin, $expectedKeys)) {
                $values[$key] = $value;
                $origins[$key] = $origin;
            }
        }

        return new ConfigSnapshot($values, $origins, $snapshot->loadedFiles());
    }

    private function shouldKeep(string $key, string $origin, array $expectedKeys): bool
    {
        if ($origin !== 'system') {
            return true;
        }
        return isset($expectedKeys[$key]);
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
        $filtered['PIPELINE'] = $pipeline;
        $filtered['PHASE'] = $phase;

        return $filtered;
    }

    private function resolveTargetPath(?string $targetPath): string
    {
        if ($targetPath !== null) {
            return $targetPath;
        }
        return Path::join($this->rootPath, 'var', 'config', 'config.php');
    }

    private function writeCompiled(string $path, array $values): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $payload = "<?php\n\nreturn " . var_export($values, true) . ";\n";
        file_put_contents($path, $payload);
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
