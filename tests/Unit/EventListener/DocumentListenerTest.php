<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\EventListener;

use Codeception\Test\Unit;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Model\Document\Link;
use Pimcore\Model\Document\Page;
use Tsf\AssetBundle\EventListener\DocumentListener;
use Tsf\AssetBundle\Tests\Support\SorterFactory;

/**
 * AssetStructureSorter is final, so the delegation is observed through the document the sorter
 * reaches for rather than through a test double of the sorter itself.
 */
final class DocumentListenerTest extends Unit
{
    public function testOnPostAddHandsTheDocumentToTheSorter(): void
    {
        (new DocumentListener(SorterFactory::create()))->onPostAdd(new DocumentEvent($this->sortedPage()));
    }

    public function testOnPreUpdateHandsTheDocumentToTheSorter(): void
    {
        (new DocumentListener(SorterFactory::create()))->onPreUpdate(new DocumentEvent($this->sortedPage()));
    }

    public function testDocumentsWithoutEditablesAreIgnored(): void
    {
        $link = $this->createMock(Link::class);
        $link->expects(self::never())->method('getType');

        (new DocumentListener(SorterFactory::create()))->onPostAdd(new DocumentEvent($link));
    }

    public function testDisabledDocumentSortingStopsBeforeTheEditables(): void
    {
        $page = $this->createMock(Page::class);
        $page->expects(self::never())->method('getEditables');

        $sorter = SorterFactory::create(['documents' => ['enabled' => false]]);

        (new DocumentListener($sorter))->onPostAdd(new DocumentEvent($page));
    }

    /**
     * A page whose editables have to be read exactly once by the sorter
     */
    private function sortedPage(): Page
    {
        $page = $this->createMock(Page::class);
        $page->expects(self::once())->method('getEditables')->willReturn([]);

        return $page;
    }
}
