<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\EventListener;

use Codeception\Test\Unit;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Image;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Folder;
use Tsf\AssetBundle\EventListener\DataObjectListener;
use Tsf\AssetBundle\Tests\Support\SorterFactory;

/**
 * AssetStructureSorter is final, so the delegation is observed through the object the sorter
 * reaches for rather than through a test double of the sorter itself.
 */
final class DataObjectListenerTest extends Unit
{
    public function testOnPostAddHandsTheObjectToTheSorter(): void
    {
        (new DataObjectListener(SorterFactory::create()))->onPostAdd(new DataObjectEvent($this->sortedObject()));
    }

    public function testOnPreUpdateHandsTheObjectToTheSorter(): void
    {
        (new DataObjectListener(SorterFactory::create()))->onPreUpdate(new DataObjectEvent($this->sortedObject()));
    }

    public function testFoldersAreIgnored(): void
    {
        $listener = new DataObjectListener(SorterFactory::create());

        $listener->onPostAdd(new DataObjectEvent($this->untouchedFolder()));
        $listener->onPreUpdate(new DataObjectEvent($this->untouchedFolder()));
    }

    public function testDisabledObjectSortingStopsBeforeTheFieldDefinitions(): void
    {
        $object = $this->createMock(Concrete::class);
        $object->method('getClassName')->willReturn('Product');
        $object->expects(self::never())->method('getClass');

        $sorter = SorterFactory::create(['data_objects' => ['enabled' => false]]);

        (new DataObjectListener($sorter))->onPostAdd(new DataObjectEvent($object));
    }

    /**
     * An object whose single field has to be read exactly once by the sorter
     */
    private function sortedObject(): Concrete
    {
        $field = new Image();
        $field->setName('image');

        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions(['image' => $field]);

        $object = $this->createMock(Concrete::class);
        $object->method('getClassName')->willReturn('Product');
        $object->method('getClass')->willReturn($classDefinition);
        $object->expects(self::once())->method('get')->with('image')->willReturn(null);

        return $object;
    }

    /**
     * A folder the listener has to drop before the sorter sees it
     */
    private function untouchedFolder(): Folder
    {
        $folder = $this->createMock(Folder::class);
        $folder->expects(self::never())->method('getChildren');

        return $folder;
    }
}
