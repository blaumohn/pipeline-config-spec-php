<?php

namespace ConfigPipelineSpec\Config;

use Symfony\Component\Filesystem\Path;

final class ConfigCompiler
{
    private string $rootPath;
    private ContextResolver $contextResolver;
    private ConfigLoader $dotenv;
    private Manifest $manifest;
    private ManifestValidator $manifestValidator;
    private ConfigPolicy $policy;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->contextResolver = new ContextResolver();
        $this->dotenv = new ConfigLoader($this->rootPath);
        $this->manifest = new Manifest($this->rootPath);
        $this->manifestValidator = new ManifestValidator();
        $this->policy = new ConfigPolicy();
    }

    public function compile(Context $context, bool $interactive, ?string $targetPath = null, array $overrides = []): string
    {
        $snapshot = $this->resolve($context, $overrides);

        $values = $this->filterAllowed($context, $snapshot->values());

        $targetPath = $targetPath ?? Path::join($this->rootPath, 'var', 'config', 'env.php');
        $this->writeCompiled($targetPath, $values);

        return $targetPath;
    }


    public function resolve(Context $context, array $overrides = []): ConfigSnapshot
    {
        $this->manifestValidator->validate($this->manifest->data());

        $base = [
            'PIPELINE' => $context->pipeline(),
            'PHASE' => $context->phase(),
        ];
        if ($context->profile() !== null) {
            $base['PROFILE'] = $context->profile();
        }

        $snapshot = $this->dotenv->load($context, array_merge($base, $overrides));
        $snapshot = $this->filterSnapshot($context, $snapshot);
        $errors = $this->policy->validate($this->manifest, $context, $snapshot);
        if ($errors !== []) {
            throw new \RuntimeException("Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
        }

        return $snapshot;
    }

    public function validate(Context $context, bool $interactive, array $overrides = []): ConfigSnapshot
    {
        return $this->resolve($context, $overrides);
    }

    private function filterSnapshot(Context $context, ConfigSnapshot $snapshot): ConfigSnapshot
    {
        $phaseConfig = $this->manifest->resolvePhaseConfig($context);
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

    public function resolveContext(array $defaults, array $overrides = []): Context
    {
        return $this->contextResolver->resolve($defaults, $overrides);
    }

    private function filterAllowed(Context $context, array $values): array
    {
        $phaseConfig = $this->manifest->resolvePhaseConfig($context);
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

    private function writeCompiled(string $path, array $values): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $payload = "<?php\n\nreturn " . var_export($values, true) . ";\n";
        file_put_contents($path, $payload);
    }
}
