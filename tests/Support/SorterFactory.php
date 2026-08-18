<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tsf\AssetBundle\Service\Assets\AssetStructureSorter;
use Tsf\AssetBundle\Service\Assets\PathResolver;
use Tsf\AssetBundle\Service\Config\SortingConfiguration;

/**
 * Builds real sorters for the tests. AssetStructureSorter, PathResolver and SortingConfiguration
 * are final and have no interface, so they cannot be doubled and are wired for real instead.
 */
final class SorterFactory
{
    /**
     * @param array<string, mixed> $overrides Parts of the tsf_asset.sorting tree to merge over the defaults
     */
    public static function create(array $overrides = [], ?LoggerInterface $logger = null): AssetStructureSorter
    {
        return new AssetStructureSorter(
            new SortingConfiguration(self::sortingConfig($overrides)),
            new PathResolver($logger ?? new NullLogger())
        );
    }

    /**
     * A sorter whose pattern never resolves, so it stops before touching the database
     */
    public static function createCollecting(CollectedAssetLogger $logger, array $overrides = []): AssetStructureSorter
    {
        $unresolvable = ['path' => '/{field:a_field_that_does_not_exist}', 'asset_types' => []];

        return self::create(
            [
                'data_objects' => $unresolvable + ($overrides['data_objects'] ?? []),
                'documents' => $unresolvable + ($overrides['documents'] ?? []),
            ],
            $logger
        );
    }

    /**
     * A processed tsf_asset.sorting tree, with both sections switched on
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function sortingConfig(array $overrides = []): array
    {
        $config = [
            'data_objects' => [
                'enabled' => true,
                'path' => '/{class}',
                'asset_types' => [],
                'classes' => [],
            ],
            'documents' => [
                'enabled' => true,
                'path' => '/documents/{doctype}',
                'asset_types' => [],
            ],
        ];

        foreach ($overrides as $section => $values) {
            $config[$section] = $values + $config[$section];
        }

        return $config;
    }
}
