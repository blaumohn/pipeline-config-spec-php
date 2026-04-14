<?php

namespace PipelineConfigSpec;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PipelineConfigSpec\Internal\ConfigSnapshot;

final class PipelineConfigService
{
    private string $rootPath;
    private string $configDir;

    public function __construct(string $rootPath, string $configDir = 'config')
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->configDir = $this->normalizeConfigDir($configDir);
    }

    public function values(string $pipeline, string $phase, array $overrides = []): array
    {
        $snapshot = $this->resolveSnapshot($pipeline, $phase, $overrides);
        $values = $snapshot->values();
        return $values;
    }

    public function validate(string $pipeline, string $phase, array $overrides = []): void
    {
        $compiler = $this->compiler();
        $compiler->validate($pipeline, $phase, $overrides);
    }

    public function compile(
        string $pipeline,
        string $phase,
        ?string $targetPath = null,
        array $overrides = []
    ): string {
        $compiler = $this->compiler();
        $path = $compiler->compile($pipeline, $phase, $targetPath, $overrides);
        return $path;
    }

    public function describe(string $pipeline, string $phase, array $overrides = []): array
    {
        $snapshot = $this->resolveSnapshot($pipeline, $phase, $overrides);
        $data = $this->describeSnapshot($pipeline, $phase, $snapshot);
        return $data;
    }

    private function describeSnapshot(string $pipeline, string $phase, ConfigSnapshot $snapshot): array
    {
        $contextData = [
            'pipeline' => $pipeline,
            'phase' => $phase,
        ];
        $data = [
            'context' => $contextData,
            'files' => $snapshot->loadedFiles(),
            'values' => $snapshot->values(),
            'origins' => $snapshot->origins(),
        ];
        return $data;
    }

    private function resolveSnapshot(
        string $pipeline,
        string $phase,
        array $overrides
    ): ConfigSnapshot {
        $compiler = $this->compiler();
        $snapshot = $compiler->resolve($pipeline, $phase, $overrides);
        return $snapshot;
    }

    private function compiler(): ConfigCompiler
    {
        $compiler = new ConfigCompiler($this->rootPath, $this->configDir);
        return $compiler;
    }

    private function normalizeConfigDir(string $configDir): string
    {
        $trimmed = trim($configDir, DIRECTORY_SEPARATOR);
        if ($trimmed === '') {
            return 'config';
        }
        return $trimmed;
    }
}
