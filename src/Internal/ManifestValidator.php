<?php

namespace PipelineConfigSpec\Internal;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
final class ManifestValidator
{
    public function validate(array $data): void
    {
        $treeBuilder = new TreeBuilder('manifest');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('variables')
                    ->useAttributeAsKey('group')
                    ->arrayPrototype()
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->arrayNode('sources')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('meta')
                                    ->children()
                                        ->scalarNode('desc')->end()
                                        ->scalarNode('notes')->end()
                                        ->scalarNode('example')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pipelines')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->useAttributeAsKey('phase')
                        ->arrayPrototype()
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        $processor = new Processor();
        $processor->process($treeBuilder->buildTree(), ['manifest' => $data]);
    }
}
