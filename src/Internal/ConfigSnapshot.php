<?php

namespace PipelineConfigSpec\Internal;

/**
 * @internal
 */
final class ConfigSnapshot
{
    private array $values;
    private array $sources;
    private array $loadedFiles;

    public function __construct(array $values, array $sources, array $loadedFiles)
    {
        $this->values = $values;
        $this->sources = $sources;
        $this->loadedFiles = $loadedFiles;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function sources(): array
    {
        return $this->sources;
    }

    public function loadedFiles(): array
    {
        return $this->loadedFiles;
    }
}
