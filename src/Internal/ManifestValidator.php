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
        $pipelines = $data['pipelines'] ?? null;
        if (!is_array($groups)) {
            throw new \RuntimeException('Manifest-Feld variable-groups fehlt oder ist ungueltig.');
        }
        if (!is_array($pipelines)) {
            throw new \RuntimeException('Manifest-Feld pipelines fehlt oder ist ungueltig.');
        }
        $this->validateGroups($groups);
        $this->validatePipelines($pipelines);
    }

    private function validateGroups(array $groups): void
    {
        foreach ($groups as $group) {
            if (!is_array($group)) {
                throw new \RuntimeException('Jede variable-group muss ein Objekt sein.');
            }
            $this->requireString($group['key'] ?? null, 'variable-group.key fehlt.');
            $variables = $group['variables'] ?? null;
            if (!is_array($variables)) {
                throw new \RuntimeException('variable-group.variables fehlt oder ist ungueltig.');
            }
            $this->validateVariableEntries($variables, 'variable-group');
        }
    }

    private function validatePipelines(array $pipelines): void
    {
        foreach ($pipelines as $phases) {
            if (!is_array($phases)) {
                throw new \RuntimeException('Jede Pipeline muss ein Phasen-Mapping sein.');
            }
            foreach ($phases as $rules) {
                if (!is_array($rules)) {
                    throw new \RuntimeException('Jede Phase muss eine Liste von Gruppen-Referenzen sein.');
                }
                foreach ($rules as $rule) {
                    $this->validatePhaseRule($rule);
                }
            }
        }
    }

    private function validatePhaseRule(mixed $rule): void
    {
        if (!is_array($rule)) {
            throw new \RuntimeException('Jede Gruppen-Referenz muss ein Objekt sein.');
        }

        $this->requireString($rule['group-key'] ?? null, 'group-key fehlt.');
        $selectAll = ($rule['select'] ?? null) === '*';
        $variables = $rule['variables'] ?? null;
        if (!$selectAll && !is_array($variables)) {
            throw new \RuntimeException('Gruppen-Referenz braucht select: "*" oder variables.');
        }
        if (is_array($variables)) {
            $this->validateVariableEntries($variables, 'Gruppen-Referenz');
        }
    }

    private function validateVariableEntries(array $variables, string $label): void
    {
        foreach ($variables as $entry) {
            if (!is_array($entry)) {
                throw new \RuntimeException($label . ' enthaelt einen ungueltigen Variablen-Eintrag.');
            }
            $this->requireString($entry['key'] ?? null, $label . '.variables[].key fehlt.');
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
