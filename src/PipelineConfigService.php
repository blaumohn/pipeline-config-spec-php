<?php

namespace PipelineConfigSpec;

use PipelineConfigSpec\Internal\ConfigCompiler;
use PipelineConfigSpec\Internal\ConfigSnapshot;
use Symfony\Component\Filesystem\Path;

final class PipelineConfigService
{
    private string $rootPath;
    private string $configDir;

    public function __construct(string $rootPath, string $configDir = 'pipeline-config')
    {
        $this->rootPath = Path::normalize($rootPath);
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

    public function cliVarsForPhase(string $pipeline, string $phase): array
    {
        return $this->compiler()->cliVarsForPhase($pipeline, $phase);
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
            'sources' => $snapshot->sources(),
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
        if ($configDir === '') {
            return 'pipeline-config';
        }
        $normalized = trim(Path::normalize($configDir), '/');
        return $normalized !== '' ? $normalized : 'pipeline-config';
    }
}
