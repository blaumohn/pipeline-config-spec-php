<?php

namespace PipelineConfigSpec\Internal;

/**
 * @internal
 */
final class ConfigSnapshot
{
    private array $values;
    private array $origins;
    private array $loadedFiles;

    public function __construct(array $values, array $origins, array $loadedFiles)
    {
        $this->values = $values;
        $this->origins = $origins;
        $this->loadedFiles = $loadedFiles;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function origins(): array
    {
        return $this->origins;
    }

    public function loadedFiles(): array
    {
        return $this->loadedFiles;
    }
}
