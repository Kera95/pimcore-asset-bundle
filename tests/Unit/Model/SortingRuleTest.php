<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\Model;

use Codeception\Test\Unit;
use Pimcore\Model\Asset;
use Tsf\AssetBundle\Model\SortingRule;

final class SortingRuleTest extends Unit
{
    public function testGetPathReturnsTheFallbackPattern(): void
    {
        $rule = new SortingRule('/{class}', ['image' => '/images']);

        self::assertSame('/{class}', $rule->getPath());
    }

    public function testGetPathForAssetUsesTheTypeOverride(): void
    {
        $rule = new SortingRule('/{class}', ['image' => '/images/{char:1}']);

        self::assertSame('/images/{char:1}', $rule->getPathForAsset($this->assetOfType('image')));
    }

    public function testGetPathForAssetFallsBackWhenTheTypeHasNoOverride(): void
    {
        $rule = new SortingRule('/{class}', ['image' => '/images']);

        self::assertSame('/{class}', $rule->getPathForAsset($this->assetOfType('video')));
    }

    public function testGetPathForAssetFallsBackWithoutAnyOverride(): void
    {
        $rule = new SortingRule('/{class}');

        self::assertSame('/{class}', $rule->getPathForAsset($this->assetOfType('image')));
    }

    private function assetOfType(string $type): Asset
    {
        $asset = $this->createMock(Asset::class);
        $asset->method('getType')->willReturn($type);

        return $asset;
    }
}
