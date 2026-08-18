<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tsf\AssetBundle\Service\Config\SortingConfiguration;

final class TsfAssetExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('tsf_asset.config', $config);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('services.yaml');

        $container->getDefinition(SortingConfiguration::class)
            ->setArgument('$config', $config['sorting']);
    }
}
