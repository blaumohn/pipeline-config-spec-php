<?php

namespace EnvPipelineSpec\Env;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

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
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pipelines')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->useAttributeAsKey('phase')
                        ->arrayPrototype()
                            ->children()
                                ->arrayNode('required')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('allowed')
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        $processor = new Processor();
        $processor->process($treeBuilder->buildTree(), ['manifest' => $data]);
    }
}
