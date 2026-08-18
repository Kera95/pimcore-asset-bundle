<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\Service\Assets;

use Codeception\Test\Unit;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Image as ImageFieldDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\Document\Editable\Image as ImageEditable;
use Pimcore\Model\Document\Editable\Input as InputEditable;
use Pimcore\Model\Document\Editable\Pdf as PdfEditable;
use Pimcore\Model\Document\Editable\Relation as RelationEditable;
use Pimcore\Model\Document\Editable\Relations as RelationsEditable;
use Pimcore\Model\Document\Editable\Video as VideoEditable;
use Pimcore\Model\Document\Link;
use Pimcore\Model\Document\Page;
use Tsf\AssetBundle\Tests\Support\CollectedAssetLogger;
use Tsf\AssetBundle\Tests\Support\SorterFactory;

/**
 * Covers which assets the sorter picks up. The move itself needs a database and is out of scope
 * for the unit suite, so the sorter is pointed at a pattern that never resolves; see
 * {@see CollectedAssetLogger}.
 */
final class AssetStructureSorterTest extends Unit
{
    private CollectedAssetLogger $logger;

    protected function _before(): void
    {
        $this->logger = new CollectedAssetLogger();
    }

    public function testAPlainAssetFieldIsCollected(): void
    {
        $this->sortObject(['image' => $this->asset(1)]);

        self::assertSame([1], $this->logger->getAssetIds());
    }

    public function testSeveralFieldsAreCollected(): void
    {
        $this->sortObject(['image' => $this->asset(1), 'attachment' => $this->asset(2)]);

        self::assertSame([1, 2], $this->logger->getAssetIds());
    }

    public function testAssetFoldersAreSkipped(): void
    {
        $this->sortObject(['image' => $this->createMock(Asset\Folder::class), 'attachment' => $this->asset(2)]);

        self::assertSame([2], $this->logger->getAssetIds());
    }

    public function testTheSameAssetInTwoFieldsIsCollectedOnce(): void
    {
        $asset = $this->asset(1);

        $this->sortObject(['image' => $asset, 'copy' => $asset]);

        self::assertSame([1], $this->logger->getAssetIds());
    }

    public function testValuesWithoutAnAssetAreSkipped(): void
    {
        $this->sortObject([
            'sku' => 'AB-1000',
            'count' => 3,
            'nothing' => null,
            'other' => $this->createMock(Concrete::class),
        ]);

        self::assertSame([], $this->logger->getAssetIds());
    }

    public function testAssetsInsideAnArrayAreCollected(): void
    {
        $this->sortObject(['images' => [$this->asset(1), 'not an asset', $this->asset(2)]]);

        self::assertSame([1, 2], $this->logger->getAssetIds());
    }

    public function testAssetsInsideNestedArraysAreCollected(): void
    {
        $this->sortObject(['images' => [[$this->asset(1)], [$this->asset(2)]]]);

        self::assertSame([1, 2], $this->logger->getAssetIds());
    }

    public function testTheImageOfAHotspotimageIsCollected(): void
    {
        $this->sortObject(['image' => new Hotspotimage($this->image(1))]);

        self::assertSame([1], $this->logger->getAssetIds());
    }

    public function testAnEmptyHotspotimageIsSkipped(): void
    {
        $this->sortObject(['image' => new Hotspotimage()]);

        self::assertSame([], $this->logger->getAssetIds());
    }

    public function testTheItemsOfAnImageGalleryAreCollected(): void
    {
        $gallery = new ImageGallery([new Hotspotimage($this->image(1)), new Hotspotimage($this->image(2))]);

        $this->sortObject(['gallery' => $gallery]);

        self::assertSame([1, 2], $this->logger->getAssetIds());
    }

    public function testTheElementOfAnElementMetadataIsCollected(): void
    {
        // getElement() lazy loads through the element cache, so the metadata itself is doubled
        $metadata = $this->createMock(ElementMetadata::class);
        $metadata->method('getElement')->willReturn($this->asset(1));

        $this->sortObject(['images' => [$metadata]]);

        self::assertSame([1], $this->logger->getAssetIds());
    }

    public function testObjectsAreSkippedWhenSortingIsDisabled(): void
    {
        $this->sortObject(['image' => $this->asset(1)], ['data_objects' => ['enabled' => false]]);

        self::assertSame([], $this->logger->getAssetIds());
    }

    /**
     * @param class-string $editableClass
     * @dataProvider documentEditableProvider
     */
    public function testDocumentEditablesAreCollected(string $editableClass, string $method, bool $returnsList): void
    {
        $asset = match ($editableClass) {
            ImageEditable::class => $this->image(1),
            VideoEditable::class => $this->video(1),
            default              => $this->asset(1),
        };

        $editable = $this->createMock($editableClass);
        $editable->method($method)->willReturn($returnsList ? [$asset] : $asset);

        $this->sortDocument([$editable]);

        self::assertSame([1], $this->logger->getAssetIds());
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: bool}>
     */
    public static function documentEditableProvider(): array
    {
        return [
            'image'     => [ImageEditable::class, 'getImage', false],
            'video'     => [VideoEditable::class, 'getVideoAsset', false],
            'pdf'       => [PdfEditable::class, 'getElement', false],
            'relation'  => [RelationEditable::class, 'getElement', false],
            'relations' => [RelationsEditable::class, 'getElements', true],
        ];
    }

    public function testEditablesWithoutAnAssetAreSkipped(): void
    {
        $input = $this->createMock(InputEditable::class);
        $image = $this->createMock(ImageEditable::class);
        $image->method('getImage')->willReturn(null);

        $this->sortDocument([$input, $image]);

        self::assertSame([], $this->logger->getAssetIds());
    }

    public function testDocumentsWithoutEditablesAreSkipped(): void
    {
        $sorter = SorterFactory::createCollecting($this->logger);

        $sorter->sortDocumentAssets($this->createMock(Link::class));

        self::assertSame([], $this->logger->getAssetIds());
    }

    public function testDocumentsAreSkippedWhenSortingIsDisabled(): void
    {
        $image = $this->createMock(ImageEditable::class);
        $image->method('getImage')->willReturn($this->image(1));

        $this->sortDocument([$image], ['documents' => ['enabled' => false]]);

        self::assertSame([], $this->logger->getAssetIds());
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $configOverrides
     */
    private function sortObject(array $fields, array $configOverrides = []): void
    {
        $fieldDefinitions = [];

        foreach (array_keys($fields) as $fieldName) {
            $definition = new ImageFieldDefinition();
            $definition->setName($fieldName);
            $fieldDefinitions[$fieldName] = $definition;
        }

        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions($fieldDefinitions);

        $object = $this->createMock(Concrete::class);
        $object->method('getId')->willReturn(42);
        $object->method('getClassName')->willReturn('Product');
        $object->method('getClass')->willReturn($classDefinition);
        $object->method('get')->willReturnCallback(
            static fn (string $fieldName): mixed => $fields[$fieldName] ?? null
        );

        SorterFactory::createCollecting($this->logger, $configOverrides)->sortObjectAssets($object);
    }

    /**
     * @param array<int, object> $editables
     * @param array<string, mixed> $configOverrides
     */
    private function sortDocument(array $editables, array $configOverrides = []): void
    {
        $page = $this->createMock(Page::class);
        $page->method('getId')->willReturn(7);
        $page->method('getType')->willReturn('page');
        $page->method('getEditables')->willReturn($editables);

        SorterFactory::createCollecting($this->logger, $configOverrides)->sortDocumentAssets($page);
    }

    private function asset(int $id): Asset
    {
        return $this->assetOfClass(Asset::class, $id, 'image');
    }

    private function image(int $id): Asset\Image
    {
        return $this->assetOfClass(Asset\Image::class, $id, 'image');
    }

    private function video(int $id): Asset\Video
    {
        return $this->assetOfClass(Asset\Video::class, $id, 'video');
    }

    /**
     * @template T of Asset
     * @param class-string<T> $class
     * @return T
     */
    private function assetOfClass(string $class, int $id, string $type): Asset
    {
        $asset = $this->createMock($class);
        $asset->method('getId')->willReturn($id);
        $asset->method('getFilename')->willReturn(sprintf('asset-%d.jpg', $id));
        $asset->method('getType')->willReturn($type);

        return $asset;
    }
}
