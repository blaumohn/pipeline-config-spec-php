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
        $sources = $snapshot->sources();
        foreach ($snapshot->values() as $variable => $_value) {
            $sourcePolicy = $manifest->sourcePolicyForVariable($variable);
            if ($sourcePolicy === []) {
                continue;
            }
            $sourceType = $this->sourceType($sources[$variable] ?? '');
            if (in_array($sourceType, $sourcePolicy, true)) {
                continue;
            }
            $policyLabel = implode(', ', $sourcePolicy);
            $errors[] = "Variable in falscher Quelle: {$variable} ({$sources}, erlaubt: {$policyLabel})";
        }
        return $errors;
    }

    private function sourceType(string $source): string
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
