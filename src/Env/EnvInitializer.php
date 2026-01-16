<?php

namespace EnvPipelineSpec\Env;

use Symfony\Component\Filesystem\Path;

final class EnvInitializer
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function ensureLocalEnv(bool $interactive): void
    {
        $target = Path::join($this->rootPath, '.env.local');
        if (is_file($target)) {
            return;
        }
        if (!$this->shouldAttemptSetup($interactive)) {
            return;
        }
        if (!$this->shouldSetupDemo($interactive)) {
            return;
        }
        $this->copyDemo($this->demoFile(), $target);
    }

    private function shouldAttemptSetup(bool $interactive): bool
    {
        return $this->isAutomated() || $interactive;
    }

    private function shouldSetupDemo(bool $interactive): bool
    {
        if (!$this->shouldPrompt($interactive)) {
            return true;
        }
        return $this->promptSetupDemo();
    }

    private function shouldPrompt(bool $interactive): bool
    {
        return $interactive && !$this->isAutomated();
    }

    private function isAutomated(): bool
    {
        return getenv('AUTO_ENV_SETUP') === '1';
    }

    private function promptSetupDemo(): bool
    {
        fwrite(STDOUT, "Keine .env.local gefunden. Demo-Umgebung anlegen? [y/N] ");
        $answer = trim((string) fgets(STDIN));
        return strtolower($answer) === 'y';
    }

    private function demoFile(): string
    {
        return Path::join($this->rootPath, 'tests', 'fixtures', 'env.local');
    }

    private function copyDemo(string $source, string $target): void
    {
        $content = file_get_contents($source);
        if ($content === false) {
            throw new \RuntimeException("Demo-Env nicht lesbar: {$source}");
        }
        file_put_contents($target, $content);
        fwrite(STDOUT, $target . " erstellt.\n");
    }
}
