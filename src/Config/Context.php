<?php

namespace ConfigPipelineSpec\Config;

final class Context
{
    private string $pipeline;
    private string $phase;

    public function __construct(string $pipeline, string $phase)
    {
        $this->pipeline = $pipeline;
        $this->phase = $phase;
    }

    public function pipeline(): string
    {
        return $this->pipeline;
    }

    public function phase(): string
    {
        return $this->phase;
    }
}
