<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tsf_asset');

        $treeBuilder->getRootNode()
            ->children()
            ->end();

        return $treeBuilder;
    }
}
