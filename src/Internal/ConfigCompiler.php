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

        $base = [
            'PIPELINE' => $pipeline,
            'PHASE' => $phase,
        ];

        $values = array_merge($base, $overrides);
        $snapshot = $this->loader->load($pipeline, $phase, $values);
        $snapshot = $this->filterSnapshot($pipeline, $phase, $snapshot);
        $errors = $this->policy->validate($this->manifest, $pipeline, $phase, $snapshot);
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

    private function filterSnapshot(
        string $pipeline,
        string $phase,
        ConfigSnapshot $snapshot
    ): ConfigSnapshot {
        $phaseConfig = $this->manifest->resolvePhaseConfig($pipeline, $phase);
        if ($phaseConfig === null) {
            return $snapshot;
        }

        $allowed = $this->manifest->expandAllowed($phaseConfig['allowed'] ?? []);
        $required = $this->manifest->expandRequired($phaseConfig['required'] ?? []);
        $policyKeys = $this->manifest->variableKeys();
        $keys = array_merge($allowed, $required, $policyKeys);

        $values = [];
        $sources = [];
        foreach ($snapshot->values() as $key => $value) {
            $source = $snapshot->sources()[$key] ?? '';
            if ($this->shouldKeep($key, $source, $keys)) {
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
        return $this->isAllowed($key, $allowed);
    }

    private function filterAllowed(string $pipeline, string $phase, array $values): array
    {
        $phaseConfig = $this->manifest->resolvePhaseConfig($pipeline, $phase);
        if ($phaseConfig === null) {
            return [];
        }

        $allowed = $this->manifest->expandAllowed($phaseConfig['allowed'] ?? []);
        $filtered = [];
        foreach ($values as $key => $value) {
            if ($this->isAllowed($key, $allowed)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function isAllowed(string $key, array $allowed): bool
    {
        foreach ($allowed as $rule) {
            if (!is_string($rule)) {
                continue;
            }
            if ($rule === $key) {
                return true;
            }
            if (str_ends_with($rule, '*')) {
                $prefix = substr($rule, 0, -1);
                if (str_starts_with($key, $prefix)) {
                    return true;
                }
            }
        }
        return false;
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
