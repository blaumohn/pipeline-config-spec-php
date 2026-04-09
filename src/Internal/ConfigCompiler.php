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
        $values = $this->filterAllowed($pipeline, $phase, $snapshot->values());

        $targetPath = $this->resolveTargetPath($targetPath);
        $this->writeCompiled($targetPath, $values);

        return $targetPath;
    }

    public function resolve(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        $this->manifestValidator->validate($this->manifest->data());
        $this->assertKnownContext($pipeline, $phase);
        $phaseKeys = $this->manifest->resolvePhaseKeys($pipeline, $phase);

        $baseOverrides = ['PIPELINE' => $pipeline, 'PHASE' => $phase];
        $cliOverrides = array_merge($baseOverrides, $overrides);
        $systemKeys = $this->manifest->variableKeys();
        $fileLayer = $this->loader->load($pipeline, $phase);
        $systemLayer = $this->loader->loadSystem($systemKeys);
        $cliLayer = $this->loader->loadOverrides($cliOverrides);
        $snapshot = $this->mergeLayers([$fileLayer, $systemLayer, $cliLayer]);
        $snapshot = $this->filterSnapshot($pipeline, $phase, $snapshot);
        $validationSnapshot = $this->withoutKeys($snapshot, $this->baseKeys());
        $errors = $this->policy->validate($this->manifest, $pipeline, $phase, $validationSnapshot);
        if ($errors !== []) {
            $message = "Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors);
            throw new \RuntimeException($message);
        }

        return $snapshot;
    }

    public function validate(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        return $this->resolve($pipeline, $phase, $overrides);
    }

    private function assertKnownContext(string $pipeline, string $phase): void
    {
        $errors = $this->manifest->contextErrors($pipeline, $phase);
        if ($errors === []) {
            return;
        }
        throw new \RuntimeException("Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
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

    private function filterSnapshot(
        string $pipeline,
        string $phase,
        ConfigSnapshot $snapshot
    ): ConfigSnapshot {
        $phaseKeys = $this->manifest->resolvePhaseKeys($pipeline, $phase);

        $allowed = array_flip(array_merge($phaseKeys, $this->manifest->variableKeys()));
        $values = [];
        $sources = [];
        foreach ($snapshot->values() as $key => $value) {
            $source = $snapshot->sources()[$key] ?? '';
            if ($this->shouldKeep($key, $source, $allowed)) {
                $values[$key] = $value;
                $sources[$key] = $source;
            }
        }

        return new ConfigSnapshot($values, $sources, $snapshot->loadedFiles());
    }

    private function shouldKeep(string $key, string $source, array $allowed): bool
    {
        if ($source !== 'system') {
            return true;
        }
        return isset($allowed[$key]);
    }

    private function baseKeys(): array
    {
        return ['PIPELINE', 'PHASE'];
    }

    private function withoutKeys(ConfigSnapshot $snapshot, array $keys): ConfigSnapshot
    {
        $exclude = array_flip($keys);
        $values = array_diff_key($snapshot->values(), $exclude);
        $sources = array_diff_key($snapshot->sources(), $exclude);
        return new ConfigSnapshot($values, $sources, $snapshot->loadedFiles());
    }

    private function filterAllowed(string $pipeline, string $phase, array $values): array
    {
        $phaseKeys = $this->manifest->resolvePhaseKeys($pipeline, $phase);

        $allowed = array_flip(array_merge($phaseKeys, $this->baseKeys()));
        $filtered = [];
        foreach ($values as $key => $value) {
            if (isset($allowed[$key])) {
                $filtered[$key] = $value;
            }
        }

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
