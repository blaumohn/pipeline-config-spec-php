<?php

declare(strict_types=1);

namespace ConfigPipelineSpec\Tests;

use ConfigPipelineSpec\Config\ContextResolver;
use PHPUnit\Framework\TestCase;

final class ContextResolverTest extends TestCase
{
    public function testResolvesPipelineAndPhaseFromOverrides(): void
    {
        $resolver = new ContextResolver();
        $context = $resolver->resolve(
            ['pipeline' => 'dev', 'phase' => 'build'],
            ['pipeline' => 'prod', 'phase' => 'runtime', 'profile' => 'x']
        );

        self::assertSame('prod', $context->pipeline());
        self::assertSame('runtime', $context->phase());
    }

    public function testThrowsWhenPipelineMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $resolver = new ContextResolver();
        $resolver->resolve(['phase' => 'runtime'], []);
    }

    public function testThrowsWhenPhaseMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $resolver = new ContextResolver();
        $resolver->resolve(['pipeline' => 'dev'], []);
    }
}
