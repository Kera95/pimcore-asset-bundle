<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Service\Assets;

use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Service as AssetService;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Editable;
use Pimcore\Model\Document\PageSnippet;
use Pimcore\Model\Element\ElementDescriptor;
use Pimcore\Model\Element\ElementInterface;
use Tsf\AssetBundle\Model\SortingRule;
use Tsf\AssetBundle\Service\Config\SortingConfiguration;

final class AssetStructureSorter
{
    /**
     * @var SortingConfiguration
     */
    private SortingConfiguration $sortingConfiguration;

    /**
     * @var PathResolver
     */
    private PathResolver $pathResolver;

    /**
     * @param SortingConfiguration $sortingConfiguration
     * @param PathResolver $pathResolver
     */
    public function __construct(
        SortingConfiguration $sortingConfiguration,
        PathResolver $pathResolver
    ) {
        $this->sortingConfiguration = $sortingConfiguration;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Moves every asset referenced by the object into the configured structure
     *
     * @param Concrete $object
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    public function sortObjectAssets(Concrete $object): void
    {
        $rule = $this->sortingConfiguration->getRuleForObject($object);

        if (!$rule) {
            return;
        }

        foreach ($this->collectObjectAssets($object) as $asset) {
            $this->moveAsset($asset, $object, $rule);
        }
    }

    /**
     * Moves every asset referenced by the document editables into the configured structure
     *
     * @param Document $document
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    public function sortDocumentAssets(Document $document): void
    {
        if (!$document instanceof PageSnippet) {
            return;
        }

        $rule = $this->sortingConfiguration->getRuleForDocument($document);

        if (!$rule) {
            return;
        }

        foreach ($this->collectDocumentAssets($document) as $asset) {
            $this->moveAsset($asset, $document, $rule);
        }
    }

    /**
     * Collects the assets held by the object field values, keyed by asset id
     *
     * @param Concrete $object
     * @return array<int, Asset>
     */
    private function collectObjectAssets(Concrete $object): array
    {
        $assets = [];

        foreach (array_keys($object->getClass()->getFieldDefinitions()) as $fieldName) {
            $this->extractAssets($object->get($fieldName), $assets);
        }

        return $assets;
    }

    /**
     * Collects the assets held by the document editables, keyed by asset id
     *
     * @param PageSnippet $document
     * @return array<int, Asset>
     */
    private function collectDocumentAssets(PageSnippet $document): array
    {
        $assets = [];

        foreach ($document->getEditables() as $editable) {
            $value = match (true) {
                $editable instanceof Editable\Image     => $editable->getImage(),
                $editable instanceof Editable\Video     => $editable->getVideoAsset(),
                $editable instanceof Editable\Pdf       => $editable->getElement(),
                $editable instanceof Editable\Relation  => $editable->getElement(),
                $editable instanceof Editable\Relations => $editable->getElements(),
                default                                 => null,
            };

            $this->extractAssets($value, $assets);
        }

        return $assets;
    }

    /**
     * Walks a field or editable value and adds every asset it holds to the given list
     *
     * @param mixed $value
     * @param array<int, Asset> $assets
     * @return void
     */
    private function extractAssets(mixed $value, array &$assets): void
    {
        if ($value instanceof ElementDescriptor && $value->getType() === 'asset') {
            $this->extractAssets(Asset::getById($value->getId()), $assets);

            return;
        }

        if ($value instanceof Asset\Folder) {
            return;
        }

        if ($value instanceof Asset) {
            $assets[$value->getId()] = $value;

            return;
        }

        if ($value instanceof DataObject\Data\ImageGallery) {
            $this->extractAssets($value->getItems(), $assets);

            return;
        }

        if ($value instanceof DataObject\Data\Hotspotimage) {
            $this->extractAssets($value->getImage(), $assets);

            return;
        }

        if ($value instanceof DataObject\Data\ElementMetadata) {
            $this->extractAssets($value->getElement(), $assets);

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->extractAssets($item, $assets);
            }
        }
    }

    /**
     * Moves a single asset into the folder the rule resolves to
     *
     * @param Asset $asset
     * @param ElementInterface $element
     * @param SortingRule $rule
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    private function moveAsset(Asset $asset, ElementInterface $element, SortingRule $rule): void
    {
        $targetPath = $this->pathResolver->resolve($rule->getPathForAsset($asset), $element, $asset);

        if (!$targetPath || $targetPath === '/') {
            return;
        }

        $folder = AssetService::createFolderByPath($targetPath);

        if (!$folder instanceof Asset\Folder || $asset->getParentId() === $folder->getId()) {
            return;
        }

        $uniqueFilename = $this->getUniqueFilename($folder, $asset);

        if ($uniqueFilename !== $asset->getFilename()) {
            $asset->setFilename($uniqueFilename);
        }

        $asset->setParent($folder);
        $asset->save();
    }

    /**
     * Returns a unique filename if a different asset with the same name already exists in the given folder
     *
     * @param Asset\Folder $folder
     * @param Asset $asset
     * @return string
     */
    private function getUniqueFilename(Asset\Folder $folder, Asset $asset): string
    {
        $pathInfo = pathinfo($asset->getFilename());

        $name = $pathInfo['filename'];
        $ext  = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        $i = 1;
        $newFilename = $asset->getFilename();

        while (($existing = Asset::getByPath($folder->getFullPath() . '/' . $newFilename))
            && $existing->getId() !== $asset->getId()
        ) {
            $newFilename = sprintf('%s_%d%s', $name, $i, $ext);
            $i++;
        }

        return $newFilename;
    }
}
