<?php

namespace ConfigPipelineSpec\Config;

final class ContextResolver
{
    public function resolve(array $defaults, array $overrides = []): Context
    {
        $pipeline = $this->value('PIPELINE', $overrides['pipeline'] ?? null, $defaults['pipeline'] ?? null);
        $phase = $this->value('PHASE', $overrides['phase'] ?? null, $defaults['phase'] ?? null);
        $profile = $this->value('PROFILE', $overrides['profile'] ?? null, $defaults['profile'] ?? null);

        if ($pipeline === null || $pipeline === '') {
            throw new \RuntimeException('PIPELINE fehlt.');
        }
        if ($phase === null || $phase === '') {
            throw new \RuntimeException('PHASE fehlt.');
        }

        return new Context($pipeline, $phase, $profile);
    }

    private function value(string $envKey, ?string $override, ?string $fallback): ?string
    {
        if ($override !== null && trim($override) !== '') {
            return trim($override);
        }
        return $fallback !== null ? trim((string) $fallback) : null;
    }
}
