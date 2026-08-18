<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Model;

use Pimcore\Model\Asset;

final class SortingRule
{
    /**
     * @var string
     */
    private string $path;

    /**
     * @var array<string, string>
     */
    private array $assetTypePaths;

    /**
     * @param string $path
     * @param array<string, string> $assetTypePaths
     */
    public function __construct(
        string $path,
        array $assetTypePaths = []
    ) {
        $this->path = $path;
        $this->assetTypePaths = $assetTypePaths;
    }

    /**
     * Returns the configured fallback path pattern
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the pattern for the given asset, honouring the per asset type overrides
     *
     * @param Asset $asset
     * @return string
     */
    public function getPathForAsset(Asset $asset): string
    {
        return $this->assetTypePaths[$asset->getType()] ?? $this->path;
    }
}
