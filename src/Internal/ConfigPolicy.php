<?php

namespace PipelineConfigSpec\Internal;

/**
 * @internal
 */
final class ConfigPolicy
{
    public function validateSnapshot(ManifestPipeline $pipeline, ConfigSnapshot $snapshot): array
    {
        $errors = [];
        $pipelineVars = array_flip($pipeline->vars());

        foreach ($pipeline->vars() as $var) {
            if (!array_key_exists($var, $snapshot->values())) {
                $errors[] = "Fehlende Pflicht-Variable: {$var}";
                continue;
            }
            if ($snapshot->values()[$var] === '') {
                $errors[] = "Leerer Wert nicht erlaubt: {$var}";
            }
            $sourcePolicy = $pipeline->sourcePolicyFor($var);
            if ($sourcePolicy === []) {
                continue;
            }
            $source = (string) ($snapshot->sources()[$var] ?? '');
            $sourceType = $this->sourceType($source);
            if ($sourceType === 'default') {
                continue;
            }
            if (!in_array($sourceType, $sourcePolicy, true)) {
                $policyLabel = implode(', ', $sourcePolicy);
                $errors[] = "Variable in falscher Quelle: {$var} ({$source}, erlaubt: {$policyLabel})";
            }
        }

        foreach (array_keys($snapshot->values()) as $var) {
            if (!isset($pipelineVars[$var])) {
                $errors[] = "Überflüssige Variable: {$var}";
            }
        }

        return $errors;
    }

    private function sourceType(string $source): string
    {
        if ($source === 'cli') {
            return 'cli';
        }
        if ($source === 'default') {
            return 'default';
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
