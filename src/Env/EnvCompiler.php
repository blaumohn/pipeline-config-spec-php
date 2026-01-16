<?php

namespace EnvPipelineSpec\Env;

use Symfony\Component\Filesystem\Path;

final class EnvCompiler
{
    private string $rootPath;
    private ContextResolver $contextResolver;
    private DotenvLoader $dotenv;
    private Manifest $manifest;
    private ManifestValidator $manifestValidator;
    private EnvPolicy $policy;
    private EnvInitializer $initializer;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->contextResolver = new ContextResolver();
        $this->dotenv = new DotenvLoader($this->rootPath);
        $this->manifest = new Manifest($this->rootPath);
        $this->manifestValidator = new ManifestValidator();
        $this->policy = new EnvPolicy();
        $this->initializer = new EnvInitializer($this->rootPath);
    }

    public function compile(Context $context, bool $interactive, ?string $targetPath = null): string
    {
        $snapshot = $this->validate($context, $interactive);

        $values = $this->filterAllowed($context, $snapshot->values());

        $targetPath = $targetPath ?? Path::join($this->rootPath, 'var', 'config', 'env.php');
        $this->writeCompiled($targetPath, $values);

        return $targetPath;
    }

    public function validate(Context $context, bool $interactive): EnvSnapshot
    {
        $this->initializer->ensureLocalEnv($interactive);
        $this->manifestValidator->validate($this->manifest->data());

        $overrides = [
            'PIPELINE' => $context->pipeline(),
            'PHASE' => $context->phase(),
        ];
        if ($context->profile() !== null) {
            $overrides['PROFILE'] = $context->profile();
        }

        $snapshot = $this->dotenv->load($context, $overrides);
        $snapshot = $this->filterSnapshot($context, $snapshot);
        $errors = $this->policy->validate($this->manifest, $context, $snapshot);
        if ($errors !== []) {
            throw new \RuntimeException("Env-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
        }

        return $snapshot;
    }

    private function filterSnapshot(Context $context, EnvSnapshot $snapshot): EnvSnapshot
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

        return new EnvSnapshot($values, $sources, $snapshot->loadedFiles());
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
