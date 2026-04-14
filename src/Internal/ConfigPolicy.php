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
        $pipelinePhaseErrors = $manifest->pipelinePhaseErrors($pipeline, $phase);
        if ($pipelinePhaseErrors !== []) {
            return $pipelinePhaseErrors;
        }

        $keys = $manifest->resolvePhaseKeys($pipeline, $phase);

        return array_merge(
            $manifest->checkDisjoint($pipeline, $phase),
            $this->validateRequiredPresence($keys, $snapshot),
            $this->validateUnexpected($keys, $snapshot),
            $this->validateSources($manifest, $snapshot)
        );
    }

    private function validateRequiredPresence(array $keys, ConfigSnapshot $snapshot): array
    {
        $errors = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $snapshot->values())) {
                $errors[] = "Missing required key: {$key}";
            }
        }
        return $errors;
    }

    private function validateUnexpected(array $keys, ConfigSnapshot $snapshot): array
    {
        $expectedKeys = array_flip($keys);
        $errors = [];
        foreach ($snapshot->values() as $key => $_value) {
            if (!isset($expectedKeys[$key])) {
                $errors[] = "Unexpected key: {$key}";
            }
        }
        return $errors;
    }

    private function validateSources(Manifest $manifest, ConfigSnapshot $snapshot): array
    {
        $errors = [];
        $origins = $snapshot->origins();
        foreach ($snapshot->values() as $key => $_value) {
            $allowedSources = $manifest->sourcesForKey($key);
            if ($allowedSources === []) {
                continue;
            }
            $origin = $this->normalizeSource($origins[$key] ?? '');
            if (in_array($origin, $allowedSources, true)) {
                continue;
            }
            $policyLabel = implode(', ', $allowedSources);
            $errors[] = "Variable in falscher Quelle: {$key} ({$origin}, erlaubt: {$policyLabel})";
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
}
