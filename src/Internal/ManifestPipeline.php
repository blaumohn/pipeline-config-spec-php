<?php

namespace PipelineConfigSpec\Internal;

/**
 * @internal
 */
final class ManifestPipeline
{
    private array $allVars;

    public function __construct(
        private array $phaseVarMap,
        private array $sourcePolicy
    ) {
        $this->allVars = array_values(array_unique(
            array_reduce($phaseVarMap, fn($carry, $vars) => array_merge($carry, $vars), [])
        ));
    }

    public function vars(): array
    {
        return $this->allVars;
    }

    public function phaseVars(string $phase): array
    {
        return $this->phaseVarMap[$phase] ?? [];
    }

    public function phaseNames(): array
    {
        return array_keys($this->phaseVarMap);
    }

    public function sourcePolicyFor(string $var): array
    {
        return $this->sourcePolicy[$var] ?? [];
    }
}
