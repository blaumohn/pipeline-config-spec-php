<?php

namespace PipelineConfigSpec\Internal;

/**
 * @internal
 */
final class ManifestValidator
{
    public function validate(array $data): void
    {
        $groups = $data['variable-groups'] ?? null;
        $phases = $data['phases'] ?? null;
        $pipelines = $data['pipelines'] ?? null;
        if (!is_array($groups)) {
            throw new \RuntimeException('Manifest-Feld variable-groups fehlt oder ist ungueltig.');
        }
        if (!is_array($phases)) {
            throw new \RuntimeException('Manifest-Feld phases fehlt oder ist ungueltig.');
        }
        if (!is_array($pipelines)) {
            throw new \RuntimeException('Manifest-Feld pipelines fehlt oder ist ungueltig.');
        }
        $this->validateGroups($groups);
        $phaseKeys = $this->validatePhases($phases);
        $this->validatePipelines($pipelines, $phaseKeys);
    }

    private function validateGroups(array $groups): void
    {
        foreach ($groups as $groupKey => $variables) {
            $this->requireString($groupKey, 'variable-group.key fehlt.');
            if (!is_array($variables)) {
                throw new \RuntimeException('variable-group.variables fehlt oder ist ungueltig.');
            }
            foreach ($variables as $key => $definition) {
                $this->requireString($key, 'Variablen-key fehlt.');
                if (!is_array($definition)) {
                    throw new \RuntimeException('Variablen-Definition muss ein Objekt sein.');
                }
            }
        }
    }

    private function validatePhases(array $phases): array
    {
        $phaseKeys = [];
        foreach ($phases as $key => $phase) {
            $key = $this->requireString($key, 'phases.<key> fehlt.');
            if ($phase === null) {
                $phase = [];
            }
            if (!is_array($phase)) {
                throw new \RuntimeException('Jede Phase muss ein Objekt sein.');
            }
            $phaseKeys[$key] = true;
            $this->validateGroupRules($phase);
        }
        return $phaseKeys;
    }

    private function validatePipelines(array $pipelines, array $phaseKeys): void
    {
        foreach ($pipelines as $pipeline => $phases) {
            $pipeline = $this->requireString($pipeline, 'Pipeline-key fehlt.');
            if ($pipeline === 'common') {
                throw new \RuntimeException('common ist keine Pipeline im Manifest.');
            }
            if (!is_array($phases)) {
                throw new \RuntimeException('Jede Pipeline muss ein Phasen-Mapping sein.');
            }
            foreach ($phases as $phase => $rules) {
                $phase = $this->requireString($phase, 'Phasen-key fehlt.');
                if (!isset($phaseKeys[$phase])) {
                    throw new \RuntimeException("Unbekannte Phase im Pipeline-Mapping: {$phase}");
                }
                if (!is_array($rules)) {
                    throw new \RuntimeException('Jede Pipeline-Phase muss ein Gruppen-Mapping sein.');
                }
                $this->validateGroupRules($rules);
            }
        }
    }

    private function validateGroupRules(array $rules): void
    {
        foreach ($rules as $groupKey => $selector) {
            $this->requireString($groupKey, 'group-key fehlt.');
            if ($selector === '*') {
                continue;
            }
            if (!is_array($selector)) {
                throw new \RuntimeException('Gruppen-Referenz braucht "*" oder eine Variablenliste.');
            }
            $this->validateVariableReferences($selector);
        }
    }

    private function validateVariableReferences(array $variables): void
    {
        foreach ($variables as $key) {
            $this->requireString($key, 'Variablen-Referenz fehlt.');
        }
    }

    private function requireString(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException($message);
        }
        return trim($value);
    }
}
