<?php

namespace EnvPipelineSpec\Env;

final class Context
{
    private string $pipeline;
    private string $phase;
    private ?string $profile;

    public function __construct(string $pipeline, string $phase, ?string $profile)
    {
        $this->pipeline = $pipeline;
        $this->phase = $phase;
        $this->profile = $profile !== '' ? $profile : null;
    }

    public function pipeline(): string
    {
        return $this->pipeline;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function profile(): ?string
    {
        return $this->profile;
    }
}
