<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tsf\AssetBundle\Service\Assets\PathTokens;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_OBJECT_PATH = '/{class}/{char:1}/{char:2}';

    public const DEFAULT_DOCUMENT_PATH = '/documents/{doctype}/{char:1}/{char:2}';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tsf_asset');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('sorting')
                    ->addDefaultsIfNotSet()
                    ->info('Moves assets referenced by an element into a generated folder structure when the element is saved.')
                    ->children()
                        ->append($this->buildDataObjectsNode())
                        ->append($this->buildDocumentsNode())
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Sorting rules applied when a DataObject is saved
     *
     * @return ArrayNodeDefinition
     */
    private function buildDataObjectsNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('data_objects');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Master switch. When disabled the listener returns without touching any asset.')
                ->end()
                ->scalarNode('path')
                    ->defaultValue(self::DEFAULT_OBJECT_PATH)
                    ->info('Fallback path pattern for every class without its own rule. ' . PathTokens::getInfoLine())
                    ->validate()
                        ->always($this->validatePath())
                    ->end()
                ->end()
                ->append($this->buildAssetTypesNode())
                ->arrayNode('classes')
                    ->info('Per class overrides, keyed by DataObject class name (e.g. Product).')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('enabled')
                                ->defaultTrue()
                                ->info('Set to false to exclude this class from sorting.')
                            ->end()
                            ->scalarNode('path')
                                ->defaultNull()
                                ->info('Path pattern for this class. Falls back to sorting.data_objects.path when null.')
                                ->validate()
                                    ->always($this->validatePath())
                                ->end()
                            ->end()
                            ->append($this->buildAssetTypesNode())
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Sorting rules applied when a Pimcore Document is saved
     *
     * @return ArrayNodeDefinition
     */
    private function buildDocumentsNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('documents');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->defaultFalse()
                    ->info('Master switch. Disabled by default, enable to sort assets referenced by document editables.')
                ->end()
                ->scalarNode('path')
                    ->defaultValue(self::DEFAULT_DOCUMENT_PATH)
                    ->info('Path pattern for documents. ' . PathTokens::getInfoLine())
                    ->validate()
                        ->always($this->validatePath())
                    ->end()
                ->end()
                ->append($this->buildAssetTypesNode())
            ->end();

        return $node;
    }

    /**
     * Per asset type path overrides, keyed by Pimcore asset type (image, document, video, audio, text, archive)
     *
     * @return ArrayNodeDefinition
     */
    private function buildAssetTypesNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('asset_types');

        $node
            ->info('Path pattern per asset type. Overrides the path above for that type only.')
            ->useAttributeAsKey('type')
            ->scalarPrototype()
                ->validate()
                    ->always($this->validatePath())
                ->end()
            ->end();

        return $node;
    }

    /**
     * Rejects patterns that are empty, not absolute, or contain invalid tokens
     *
     * @return callable
     */
    private function validatePath(): callable
    {
        return static function (?string $path): ?string {
            if ($path === null) {
                return $path;
            }

            if ($path === '') {
                throw new \InvalidArgumentException('The path pattern cannot be empty.');
            }

            if (!str_starts_with($path, '/')) {
                throw new \InvalidArgumentException(
                    sprintf('The path pattern "%s" must be absolute and start with a "/".', $path)
                );
            }

            if (PathTokens::hasMalformedToken($path)) {
                throw new \InvalidArgumentException(
                    sprintf('The path pattern "%s" contains a malformed token.', $path)
                );
            }

            foreach (PathTokens::getUnknownTokens($path) as $unknownToken) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The path pattern "%s" uses the unknown token "{%s}". Available tokens: %s.',
                        $path,
                        $unknownToken,
                        implode(', ', PathTokens::getNames())
                    )
                );
            }

            return $path;
        };
    }
}
