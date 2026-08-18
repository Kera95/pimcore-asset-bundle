<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Records the asset ids PathResolver reports as unsortable.
 *
 * AssetStructureSorter moves assets through Pimcore's model layer, which needs a database. Pointing
 * it at a pattern that cannot resolve makes it stop right before the move, so the warnings the
 * resolver emits are a database free way to observe which assets the sorter actually collected.
 */
final class CollectedAssetLogger extends AbstractLogger
{
    /**
     * @var array<int, int>
     */
    private array $assetIds = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (preg_match('/asset (\d+):/', (string) $message, $matches) === 1) {
            $this->assetIds[] = (int) $matches[1];
        }
    }

    /**
     * The ids the sorter handed to the resolver, in the order they were collected
     *
     * @return array<int, int>
     */
    public function getAssetIds(): array
    {
        return $this->assetIds;
    }
}
