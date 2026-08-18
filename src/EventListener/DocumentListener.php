<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\EventListener;

use Pimcore\Event\Model\DocumentEvent;
use Tsf\AssetBundle\Service\Assets\AssetStructureSorter;

final class DocumentListener
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
     * Document Post-add listener, the document only has an id once it has been added
     *
     * @param DocumentEvent $event
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    public function onPostAdd(DocumentEvent $event): void
    {
        $this->assetStructureSorter->sortDocumentAssets($event->getDocument());
    }

    /**
     * Document Pre-update listener
     *
     * @param DocumentEvent $event
     * @return void
     * @throws \Pimcore\Model\Element\DuplicateFullPathException
     */
    public function onPreUpdate(DocumentEvent $event): void
    {
        $this->assetStructureSorter->sortDocumentAssets($event->getDocument());
    }
}
