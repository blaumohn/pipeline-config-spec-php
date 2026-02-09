<?php

namespace PipelineConfigSpec\Internal;

/**
 * @internal
 */
final class ConfigPolicy
{
    public function validate(
        Manifest $manifest,
        string $pipeline,
        string $phase,
        ConfigSnapshot $snapshot
    ): array {
        $errors = [];
        $phaseConfig = $manifest->resolvePhaseConfig($pipeline, $phase);
        if ($phaseConfig === null) {
            $errors[] = "Unbekannte Pipeline/Phase: {$pipeline}/{$phase}";
            return $errors;
        }

        $required = $manifest->expandRequired($phaseConfig['required'] ?? []);
        $allowed = $manifest->expandAllowed($phaseConfig['allowed'] ?? []);

        $errors = array_merge(
            $errors,
            $this->validateAllowedRequired($required, $allowed),
            $this->validateRequiredPresence($required, $snapshot),
            $this->validateUnexpected($allowed, $snapshot),
            $this->validateSources($manifest, $snapshot)
        );

        return $errors;
    }

    private function validateAllowedRequired(array $required, array $allowed): array
    {
        $errors = [];
        foreach ($required as $key) {
            if (!$this->isAllowed($key, $allowed)) {
                $errors[] = "Required key not allowed: {$key}";
            }
        }
        return $errors;
    }

    private function validateRequiredPresence(array $required, ConfigSnapshot $snapshot): array
    {
        $errors = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $snapshot->values())) {
                $errors[] = "Missing required key: {$key}";
            }
        }
        return $errors;
    }

    private function validateUnexpected(array $allowed, ConfigSnapshot $snapshot): array
    {
        $errors = [];
        foreach ($snapshot->values() as $key => $_value) {
            if (!$this->isAllowed($key, $allowed)) {
                $errors[] = "Unexpected key: {$key}";
            }
        }
        return $errors;
    }

    private function validateSources(Manifest $manifest, ConfigSnapshot $snapshot): array
    {
        $errors = [];
        $sources = $snapshot->sources();
        foreach ($snapshot->values() as $key => $_value) {
            $policy = $manifest->sourcesForKey($key);
            if ($policy === []) {
                continue;
            }
            if (!array_key_exists($key, $snapshot->values())) {
                continue;
            }
            $source = $this->normalizeSource($sources[$key] ?? '');
            if (in_array($source, $policy, true)) {
                continue;
            }
            $policyLabel = implode(', ', $policy);
            $errors[] = "Variable in falscher Quelle: {$key} ({$source}, erlaubt: {$policyLabel})";
        }

        return $errors;
    }

    private function normalizeSource(string $source): string
    {
        if ($source === 'system' || $source === 'cli') {
            return $source;
        }
        if ($this->isLocalPath($source)) {
            return 'local';
        }
        if ($source !== '') {
            return 'file';
        }
        return 'unknown';
    }

    private function isLocalPath(string $source): bool
    {
        if ($source === '') {
            return false;
        }
        return str_contains($source, '/.local/')
            || str_contains($source, '\\.local\\');
    }

    private function isAllowed(string $key, array $allowed): bool
    {
        foreach ($allowed as $rule) {
            if (!is_string($rule) || $rule === '') {
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
}
