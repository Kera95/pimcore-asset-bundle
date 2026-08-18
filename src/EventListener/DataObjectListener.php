<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Concrete;
use Tsf\AssetBundle\Service\Assets\AssetStructureSorter;

final class DataObjectListener
{
    /**
     * @var AssetStructureSorter
     */
    private AssetStructureSorter $assetStructureSorter;

    /**
     * @param AssetStructureSorter $assetStructureSorter
     */
    public function __construct(
        AssetStructureSorter $assetStructureSorter
    ) {
        $this->assetStructureSorter = $assetStructureSorter;
    }

    /**
     * DataObject Post-add listener, the object only has an id once it has been added
     *
     * @param DataObjectEvent $event
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    public function onPostAdd(DataObjectEvent $event): void
    {
        $this->sort($event);
    }

    /**
     * DataObject Pre-update listener
     *
     * @param DataObjectEvent $event
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    public function onPreUpdate(DataObjectEvent $event): void
    {
        $this->sort($event);
    }

    /**
     * Hands the saved object over to the sorter
     *
     * @param DataObjectEvent $event
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    private function sort(DataObjectEvent $event): void
    {
        $object = $event->getObject();

        if (!$object instanceof Concrete) {
            return;
        }

        $this->assetStructureSorter->sortObjectAssets($object);
    }
}
