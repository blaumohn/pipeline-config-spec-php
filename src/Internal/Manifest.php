<?php

namespace PipelineConfigSpec\Internal;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class Manifest
{
    private array $data;
    private ?array $groupKeys = null;
    private ?array $variableSourcePolicy = null;
    private ?array $phaseRules = null;
    private ?array $defaultValuesCache = null;

    public function __construct(string $rootPath, string $configDir = 'pipeline-config')
    {
        $configDir = $this->normalizeConfigDir($configDir);
        $path = Path::join($rootPath, $configDir, 'manifest.yaml');
        if (!is_file($path)) {
            throw new \RuntimeException("config.manifest.yaml fehlt: {$path}");
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException("config.manifest.yaml ungueltig: {$path}");
        }
        $this->data = $data;
        $this->validateStructure();
    }

    public function data(): array
    {
        return $this->data;
    }

    public function resolvePhaseVars(string $pipeline, string $phase): array
    {
        $commonVars = $this->expandPhaseRules($this->commonPhaseRules($phase));
        $specificVars = $this->expandPhaseRules($this->pipelinePhaseRules($pipeline, $phase));
        return array_values(array_unique(array_merge($commonVars, $specificVars)));
    }

    public function phaseNames(): array
    {
        return array_keys($this->phaseRules());
    }

    public function pipeline(string $pipeline): ManifestPipeline
    {
        $phaseVarMap = [];
        foreach ($this->phaseNames() as $phase) {
            $phaseVarMap[$phase] = $this->resolvePhaseVars($pipeline, $phase);
        }
        $allVars = array_reduce($phaseVarMap, fn($carry, $vars) => array_merge($carry, $vars), []);
        return new ManifestPipeline($phaseVarMap, $this->sourcePolicyForVars($allVars));
    }

    public function isKnownPipeline(string $pipeline): bool
    {
        return $this->hasPipeline($pipeline);
    }

    public function pipelinePhaseErrors(string $pipeline, string $phase): array
    {
        $errors = [];
        if (!$this->hasPipeline($pipeline)) {
            $errors[] = "Unbekannte Pipeline: {$pipeline}";
        }
        if (!$this->hasPhase($phase)) {
            $errors[] = "Unbekannte Phase: {$phase}";
        }
        return $errors;
    }

    public function checkDisjoint(string $pipeline, string $phase): array
    {
        $common = $this->commonPhaseRules($phase);
        $specific = $this->pipelinePhaseRules($pipeline, $phase);
        if ($common === [] || $specific === []) {
            return [];
        }
        $overlap = array_intersect(
            $this->expandPhaseRules($common),
            $this->expandPhaseRules($specific)
        );
        $errors = [];
        foreach (array_values($overlap) as $var) {
            $errors[] = "Disjunktheitsverletzung: {$var} in common.{$phase} und {$pipeline}.{$phase}";
        }
        return $errors;
    }

    public function defaultValues(): array
    {
        if ($this->defaultValuesCache !== null) {
            return $this->defaultValuesCache;
        }
        $defaults = [];
        foreach ($this->variableGroupsData() as $variables) {
            if (!is_array($variables)) {
                continue;
            }
            foreach ($variables as $key => $entry) {
                $key = $this->requireString($key, 'Variablen-key fehlt');
                if (is_array($entry) && array_key_exists('default', $entry)) {
                    $defaults[$key] = (string) $entry['default'];
                }
            }
        }
        $this->defaultValuesCache = $defaults;
        return $defaults;
    }

    public function sourcePolicyForVariable(string $variable): array
    {
        $sources = $this->variableSourcePolicy();
        $value = $sources[$variable] ?? [];
        return is_array($value) ? $value : [];
    }

    public function variableNames(): array
    {
        $names = [];
        foreach ($this->variableGroups() as $groupVars) {
            $names = array_merge($names, $groupVars);
        }
        return array_values(array_unique($names));
    }

    public function variableGroupNames(): array
    {
        return array_keys($this->variableGroupsData());
    }

    private function validateStructure(): void
    {
        $loader = new SchemaLoader();
        $schema = $loader->loadObjectSchema(
            json_decode((string) file_get_contents(__DIR__ . '/manifest.schema.json'))
        );
        $result = (new Validator($loader))->schemaValidation($this->toJsonValue($this->data), $schema);
        if ($result !== null) {
            $errors = (new ErrorFormatter())->format($result);
            throw new \RuntimeException(
                "manifest.yaml ungueltig:\n- " . implode("\n- ", $this->flattenErrors($errors))
            );
        }
        $this->assertNoPipelineNamedCommon();
    }

    private function toJsonValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($value === [] || !array_is_list($value)) {
            $obj = new \stdClass();
            foreach ($value as $k => $v) {
                $obj->{(string) $k} = $this->toJsonValue($v);
            }
            return $obj;
        }
        return array_map($this->toJsonValue(...), $value);
    }

    private function assertNoPipelineNamedCommon(): void
    {
        if (array_key_exists('common', $this->data['pipelines'] ?? [])) {
            throw new \RuntimeException('common ist keine Pipeline im Manifest.');
        }
    }

    private function flattenErrors(array $errors, string $prefix = ''): array
    {
        $messages = [];
        foreach ($errors as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (!is_array($value)) {
                $messages[] = $path . ': ' . $value;
                continue;
            }
            $messages = array_merge($messages, $this->flattenErrors($value, $path));
        }
        return $messages;
    }

    private function sourcePolicyForVars(array $vars): array
    {
        $policy = [];
        foreach (array_unique($vars) as $var) {
            $varPolicy = $this->sourcePolicyForVariable($var);
            if ($varPolicy !== []) {
                $policy[$var] = $varPolicy;
            }
        }
        return $policy;
    }

    private function pipelines(): array
    {
        $pipelines = $this->data['pipelines'] ?? [];
        return is_array($pipelines) ? $pipelines : [];
    }

    private function variableGroupsData(): array
    {
        $groups = $this->data['variable-groups'] ?? [];
        return is_array($groups) ? $groups : [];
    }

    private function phasesData(): array
    {
        $phases = $this->data['phases'] ?? [];
        return is_array($phases) ? $phases : [];
    }

    private function hasPipeline(string $pipeline): bool
    {
        if ($pipeline === 'common') {
            return false;
        }
        return array_key_exists($pipeline, $this->pipelines());
    }

    private function hasPhase(string $phase): bool
    {
        return array_key_exists($phase, $this->phaseRules());
    }

    private function commonPhaseRules(string $phase): array
    {
        $rules = $this->phaseRules()[$phase] ?? [];
        return is_array($rules) ? $rules : [];
    }

    private function pipelinePhaseRules(string $pipeline, string $phase): array
    {
        $pipelineRules = $this->pipelines()[$pipeline] ?? [];
        if (!is_array($pipelineRules)) {
            return [];
        }
        $rules = $pipelineRules[$phase] ?? [];
        return is_array($rules) ? $rules : [];
    }

    private function expandPhaseRules(array $rules): array
    {
        $groups = $this->variableGroups();
        $expanded = [];
        foreach ($rules as $groupKey => $selector) {
            $expanded = array_merge($expanded, $this->expandRule((string) $groupKey, $selector, $groups));
        }
        return array_values(array_unique($expanded));
    }

    private function expandRule(string $groupKey, mixed $selector, array $groups): array
    {
        $knownVars = $groups[$groupKey] ?? null;
        if ($knownVars === null) {
            throw new \RuntimeException("Unbekannter group-key: {$groupKey}");
        }
        if ($selector === '*') {
            return $knownVars;
        }
        if (!is_array($selector)) {
            throw new \RuntimeException("Variablenliste fehlt fuer group-key: {$groupKey}");
        }
        return $this->expandVariables($groupKey, $selector, $knownVars);
    }

    private function expandVariables(string $groupKey, array $variables, array $knownVars): array
    {
        $known = array_flip($knownVars);
        $expanded = [];
        foreach ($variables as $entry) {
            $var = $this->requireString($entry, "Variablen-key fehlt in group-key: {$groupKey}");
            if (!isset($known[$var])) {
                throw new \RuntimeException("Unbekannter Variablen-key {$var} in group-key: {$groupKey}");
            }
            $expanded[] = $var;
        }
        return array_values(array_unique($expanded));
    }

    private function variableGroups(): array
    {
        if ($this->groupKeys !== null) {
            return $this->groupKeys;
        }
        $groups = [];
        foreach ($this->variableGroupsData() as $groupKey => $variables) {
            $groupKey = $this->requireString($groupKey, 'Gruppen-key fehlt');
            $groups[$groupKey] = $this->collectVariableNames($variables);
        }
        $this->groupKeys = $groups;
        return $groups;
    }

    private function collectVariableNames(mixed $variables): array
    {
        if (!is_array($variables)) {
            return [];
        }
        $names = [];
        foreach ($variables as $key => $_entry) {
            $names[] = $this->requireString($key, 'Variablen-key fehlt');
        }
        return array_values(array_unique($names));
    }

    private function variableSourcePolicy(): array
    {
        if ($this->variableSourcePolicy !== null) {
            return $this->variableSourcePolicy;
        }
        $sources = [];
        foreach ($this->variableGroupsData() as $variables) {
            if (!is_array($variables)) {
                continue;
            }
            foreach ($variables as $key => $entry) {
                $key = $this->requireString($key, 'Variablen-key fehlt');
                if (!is_array($entry)) {
                    continue;
                }
                $value = $entry['sources'] ?? [];
                if (is_array($value)) {
                    $sources[$key] = array_values(array_filter($value, 'is_string'));
                }
            }
        }
        $this->variableSourcePolicy = $sources;
        return $sources;
    }

    private function phaseRules(): array
    {
        if ($this->phaseRules !== null) {
            return $this->phaseRules;
        }
        $rules = [];
        foreach ($this->phasesData() as $key => $phase) {
            $key = $this->requireString($key, 'Phasen-key fehlt');
            if ($phase === null) {
                $phase = [];
            }
            if (!is_array($phase)) {
                continue;
            }
            $rules[$key] = $phase;
        }
        $this->phaseRules = $rules;
        return $rules;
    }

    private function requireString(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException($message);
        }
        return trim($value);
    }

    private function normalizeConfigDir(string $configDir): string
    {
        if ($configDir === '') {
            return 'pipeline-config';
        }
        $normalized = trim(Path::normalize($configDir), '/');
        return $normalized !== '' ? $normalized : 'pipeline-config';
    }
}
