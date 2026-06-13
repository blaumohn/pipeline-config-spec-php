<?php

namespace PipelineConfigSpec\Internal;

use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class ConfigCompiler
{
    private string $rootPath;
    private string $configDir;
    private ConfigLoader $loader;
    private Manifest $manifest;
    private ConfigPolicy $policy;

    public function __construct(string $rootPath, string $configDir = 'pipeline-config')
    {
        $this->rootPath = Path::normalize($rootPath);
        $this->configDir = $this->normalizeConfigDir($configDir);
        $this->loader = new ConfigLoader($this->rootPath, $this->configDir);
        $this->manifest = new Manifest($this->rootPath, $this->configDir);
        $this->policy = new ConfigPolicy();
    }

    public function compile(
        string $pipeline,
        string $phase,
        ?string $targetPath = null,
        array $overrides = []
    ): string {
        $snapshot = $this->resolve($pipeline, $phase, $overrides);
        $targetPath = $this->resolveTargetPath($targetPath);
        $payload = $this->compiledPayload($pipeline, $phase, $snapshot->values());
        $this->writeCompiled($targetPath, $payload);
        return $targetPath;
    }

    public function resolve(string $pipeline, string $phase, array $overrides = []): ConfigSnapshot
    {
        $this->assertValidPipelinePhase($pipeline, $phase);
        $full = $this->buildSnapshot($pipeline, $overrides);
        $this->assertSnapshotValid($pipeline, $full);
        return $this->filterToPhase($pipeline, $phase, $full);
    }

    public function validate(string $pipeline, array $overrides = []): void
    {
        $this->assertValidPipeline($pipeline);
        $full = $this->buildSnapshot($pipeline, $overrides);
        $this->assertSnapshotValid($pipeline, $full);
    }

    public function cliVarsForPhase(string $pipeline, string $phase): array
    {
        $vars = $this->manifest->resolvePhaseVars($pipeline, $phase);
        return array_values(array_filter($vars, $this->isCliVar(...)));
    }

    public function phaseNames(): array
    {
        return $this->manifest->phaseNames();
    }

    private function buildSnapshot(string $pipeline, array $overrides): ConfigSnapshot
    {
        $this->assertKnownConfigGroups($pipeline);
        $this->assertNoDisjointViolations($pipeline);

        $fileLayer = $this->loader->load($pipeline);
        $cliLayer  = $this->loader->loadOverrides($overrides);
        $merged    = $this->mergeLayers([$fileLayer, $cliLayer]);

        return $this->applyDefaultsForAllPhases($this->manifest->pipeline($pipeline), $merged);
    }

    private function assertSnapshotValid(string $pipeline, ConfigSnapshot $snapshot): void
    {
        $errors = $this->policy->validateSnapshot($this->manifest->pipeline($pipeline), $snapshot);
        if ($errors === []) {
            return;
        }
        throw new \RuntimeException("Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
    }

    private function applyDefaultsForAllPhases(ManifestPipeline $pipeline, ConfigSnapshot $snapshot): ConfigSnapshot
    {
        $defaults = $this->manifest->defaultValues();
        $values = $snapshot->values();
        $sources = $snapshot->sources();

        foreach ($pipeline->phaseNames() as $phase) {
            foreach ($pipeline->phaseVars($phase) as $var) {
                if (!array_key_exists($var, $values) && array_key_exists($var, $defaults)) {
                    $values[$var] = $defaults[$var];
                    $sources[$var] = 'default';
                }
            }
        }

        return new ConfigSnapshot($values, $sources, $snapshot->loadedFiles());
    }

    private function filterToPhase(string $pipeline, string $phase, ConfigSnapshot $snapshot): ConfigSnapshot
    {
        $phaseVars = array_flip($this->manifest->resolvePhaseVars($pipeline, $phase));
        $values = array_intersect_key($snapshot->values(), $phaseVars);
        $sources = array_intersect_key($snapshot->sources(), $phaseVars);
        return new ConfigSnapshot($values, $sources, $snapshot->loadedFiles());
    }

    private function assertValidPipelinePhase(string $pipeline, string $phase): void
    {
        $errors = $this->manifest->pipelinePhaseErrors($pipeline, $phase);
        if ($errors === []) {
            return;
        }
        throw new \RuntimeException("Config-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
    }

    private function assertValidPipeline(string $pipeline): void
    {
        if ($this->manifest->isKnownPipeline($pipeline)) {
            return;
        }
        throw new \RuntimeException("Unbekannte Pipeline: {$pipeline}");
    }

    private function assertKnownConfigGroups(string $pipeline): void
    {
        $known = array_flip($this->manifest->variableGroupNames());
        foreach ($this->loader->rawFileGroups($pipeline) as $file => $groups) {
            $this->assertGroupsKnown($groups, $known, $file);
        }
    }

    private function assertGroupsKnown(array $groups, array $known, string $file): void
    {
        foreach ($groups as $group) {
            if (isset($known[$group])) {
                continue;
            }
            throw new \RuntimeException("Unbekannte Variablengruppe: '{$group}' in {$file}");
        }
    }

    private function assertNoDisjointViolations(string $pipeline): void
    {
        $errors = [];
        foreach ($this->manifest->phaseNames() as $phase) {
            $errors = array_merge($errors, $this->manifest->checkDisjoint($pipeline, $phase));
        }
        if ($errors === []) {
            return;
        }
        throw new \RuntimeException("Manifest-Validierung fehlgeschlagen:\n- " . implode("\n- ", $errors));
    }

    private function mergeLayers(array $layers): ConfigSnapshot
    {
        $values = [];
        $sources = [];
        $loadedFiles = [];
        foreach ($layers as $layer) {
            if (!$layer instanceof ConfigSnapshot) {
                continue;
            }
            $values = array_merge($values, $layer->values());
            $sources = array_merge($sources, $layer->sources());
            $loadedFiles = array_merge($loadedFiles, $layer->loadedFiles());
        }
        return new ConfigSnapshot($values, $sources, $loadedFiles);
    }

    private function isCliVar(string $var): bool
    {
        $policy = $this->manifest->sourcePolicyForVariable($var);
        return $policy === [] || in_array('cli', $policy, true);
    }

    private function compiledPayload(string $pipeline, string $phase, array $values): array
    {
        return [
            'pipeline_phase' => [
                'pipeline' => $pipeline,
                'phase' => $phase,
            ],
            'values' => $values,
        ];
    }

    private function resolveTargetPath(?string $targetPath): string
    {
        if ($targetPath !== null) {
            return $targetPath;
        }
        return Path::join($this->rootPath, 'var', 'config', 'config.json');
    }

    private function writeCompiled(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        file_put_contents($path, $content);
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
