<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\Service\Config;

use Codeception\Test\Unit;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document\Page;
use Tsf\AssetBundle\Service\Config\SortingConfiguration;

final class SortingConfigurationTest extends Unit
{
    public function testObjectsFallBackToTheSectionRuleWhenTheClassHasNoOverride(): void
    {
        $configuration = new SortingConfiguration($this->config());

        $rule = $configuration->getRuleForObject($this->object('Product'));

        self::assertNotNull($rule);
        self::assertSame('/{class}', $rule->getPath());
        self::assertSame('/images', $rule->getPathForAsset($this->asset('image')));
    }

    public function testObjectsAreSkippedWhenTheSectionIsDisabled(): void
    {
        $configuration = new SortingConfiguration($this->config(['data_objects' => ['enabled' => false]]));

        self::assertNull($configuration->getRuleForObject($this->object('Product')));
    }

    public function testObjectsAreSkippedWhenTheClassIsDisabled(): void
    {
        $configuration = new SortingConfiguration($this->config([
            'data_objects' => [
                'classes' => [
                    'Product' => ['enabled' => false, 'path' => '/products', 'asset_types' => []],
                ],
            ],
        ]));

        self::assertNull($configuration->getRuleForObject($this->object('Product')));
    }

    public function testAnotherClassIsStillSortedWhenOneClassIsDisabled(): void
    {
        $configuration = new SortingConfiguration($this->config([
            'data_objects' => [
                'classes' => [
                    'Product' => ['enabled' => false, 'path' => null, 'asset_types' => []],
                ],
            ],
        ]));

        $rule = $configuration->getRuleForObject($this->object('News'));

        self::assertNotNull($rule);
        self::assertSame('/{class}', $rule->getPath());
    }

    public function testTheClassPathOverridesTheSectionPath(): void
    {
        $configuration = new SortingConfiguration($this->config([
            'data_objects' => [
                'classes' => [
                    'Product' => ['enabled' => true, 'path' => '/products/{char:1}', 'asset_types' => []],
                ],
            ],
        ]));

        $rule = $configuration->getRuleForObject($this->object('Product'));

        self::assertNotNull($rule);
        self::assertSame('/products/{char:1}', $rule->getPath());
    }

    public function testAClassWithoutItsOwnPathKeepsTheSectionPath(): void
    {
        $configuration = new SortingConfiguration($this->config([
            'data_objects' => [
                'classes' => [
                    'Product' => ['enabled' => true, 'path' => null, 'asset_types' => []],
                ],
            ],
        ]));

        $rule = $configuration->getRuleForObject($this->object('Product'));

        self::assertNotNull($rule);
        self::assertSame('/{class}', $rule->getPath());
    }

    public function testClassAssetTypesWinOverSectionAssetTypesAndTheRestIsInherited(): void
    {
        $configuration = new SortingConfiguration($this->config([
            'data_objects' => [
                'asset_types' => ['image' => '/images', 'video' => '/videos'],
                'classes' => [
                    'Product' => [
                        'enabled' => true,
                        'path' => null,
                        'asset_types' => ['image' => '/products/images'],
                    ],
                ],
            ],
        ]));

        $rule = $configuration->getRuleForObject($this->object('Product'));

        self::assertNotNull($rule);
        self::assertSame('/products/images', $rule->getPathForAsset($this->asset('image')));
        self::assertSame('/videos', $rule->getPathForAsset($this->asset('video')));
        self::assertSame('/{class}', $rule->getPathForAsset($this->asset('document')));
    }

    public function testDocumentsUseTheDocumentSection(): void
    {
        $configuration = new SortingConfiguration($this->config([
            'documents' => ['enabled' => true, 'asset_types' => ['video' => '/documents/videos']],
        ]));

        $rule = $configuration->getRuleForDocument($this->createMock(Page::class));

        self::assertNotNull($rule);
        self::assertSame('/documents/{doctype}', $rule->getPath());
        self::assertSame('/documents/videos', $rule->getPathForAsset($this->asset('video')));
    }

    public function testDocumentsAreSkippedWhenTheSectionIsDisabled(): void
    {
        $configuration = new SortingConfiguration($this->config());

        self::assertNull($configuration->getRuleForDocument($this->createMock(Page::class)));
    }

    /**
     * Mirrors a processed tsf_asset.sorting tree, with the given parts merged over the defaults
     *
     * @param array<string, array<string, mixed>> $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        $config = [
            'data_objects' => [
                'enabled' => true,
                'path' => '/{class}',
                'asset_types' => ['image' => '/images'],
                'classes' => [],
            ],
            'documents' => [
                'enabled' => false,
                'path' => '/documents/{doctype}',
                'asset_types' => [],
            ],
        ];

        foreach ($overrides as $section => $values) {
            $config[$section] = $values + $config[$section];
        }

        return $config;
    }

    private function object(string $className): Concrete
    {
        $object = $this->createMock(Concrete::class);
        $object->method('getClassName')->willReturn($className);

        return $object;
    }

    private function asset(string $type): Asset
    {
        $asset = $this->createMock(Asset::class);
        $asset->method('getType')->willReturn($type);

        return $asset;
    }
}
