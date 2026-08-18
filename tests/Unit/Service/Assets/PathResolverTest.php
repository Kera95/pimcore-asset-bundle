<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\Service\Assets;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document\Page;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tsf\AssetBundle\Service\Assets\PathResolver;

final class PathResolverTest extends Unit
{
    private PathResolver $pathResolver;

    protected function _before(): void
    {
        $this->pathResolver = new PathResolver(new NullLogger());
    }

    /**
     * @dataProvider objectTokenProvider
     */
    public function testResolveObjectTokens(string $pattern, string $expected): void
    {
        $object = $this->object(['sku' => 'AB-1000']);
        $asset = $this->asset('Summer Photo.JPG', 'image');

        self::assertSame($expected, $this->pathResolver->resolve($pattern, $object, $asset));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function objectTokenProvider(): array
    {
        return [
            'id'                  => ['/{id}', '/42'],
            'key'                 => ['/{key}', '/my-product'],
            'type'                => ['/{type}', '/object'],
            'class'               => ['/{class}', '/Product'],
            'field'               => ['/{field:sku}', '/AB-1000'],
            'filename'            => ['/{filename}', '/Summer Photo.JPG'],
            'basename'            => ['/{basename}', '/Summer Photo'],
            'extension'           => ['/{extension}', '/JPG'],
            'asset type'          => ['/{asset_type}', '/image'],
            'first character'     => ['/{char:1}', '/s'],
            'second character'    => ['/{char:2}', '/u'],
            'static segments'     => ['/assets/{class}/images', '/assets/Product/images'],
            'combined tokens'     => ['/{class}/{char:1}/{char:2}', '/Product/s/u'],
            'tokens in a segment' => ['/{class}-{id}', '/Product-42'],
        ];
    }

    public function testResolveDocumentTokens(): void
    {
        $document = $this->createMock(Page::class);
        $document->method('getId')->willReturn(7);
        $document->method('getKey')->willReturn('landing');
        $document->method('getType')->willReturn('page');

        $asset = $this->asset('header.png', 'image');

        self::assertSame(
            '/documents/page/landing/7',
            $this->pathResolver->resolve('/documents/{doctype}/{key}/{id}', $document, $asset)
        );
    }

    public function testClassTokenIsNotResolvableForDocuments(): void
    {
        $document = $this->createMock(Page::class);
        $document->method('getId')->willReturn(7);

        self::assertNull(
            $this->pathResolver->resolve('/{class}/{id}', $document, $this->asset('header.png', 'image'))
        );
    }

    public function testDoctypeTokenIsNotResolvableForObjects(): void
    {
        self::assertNull(
            $this->pathResolver->resolve('/{doctype}', $this->object(), $this->asset('header.png', 'image'))
        );
    }

    public function testDateTokenUsesTheGivenFormat(): void
    {
        $expected = '/' . (new DateTimeImmutable())->format('Y/m');

        self::assertSame(
            $expected,
            $this->pathResolver->resolve('/{date:Y/m}', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    public function testDateTokenFallsBackToAnIsoDate(): void
    {
        $expected = '/' . (new DateTimeImmutable())->format('Y-m-d');

        self::assertSame(
            $expected,
            $this->pathResolver->resolve('/{date}', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    /**
     * @dataProvider characterTokenProvider
     */
    public function testCharacterTokenIsLowercasedAndPadded(string $filename, string $pattern, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->pathResolver->resolve($pattern, $this->object(), $this->asset($filename, 'image'))
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function characterTokenProvider(): array
    {
        return [
            'lowercased'                  => ['Photo.jpg', '/{char:1}', '/p'],
            'shorter than the position'   => ['ab.jpg', '/{char:5}', '/_'],
            'extension is not counted'    => ['ab.jpg', '/{char:3}', '/_'],
            'position below one is first' => ['photo.jpg', '/{char:0}', '/p'],
            'multibyte character'         => ['Über.jpg', '/{char:1}', '/ü'],
        ];
    }

    public function testUnknownTokensAreLeftUnresolved(): void
    {
        self::assertNull(
            $this->pathResolver->resolve('/{nope}', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    public function testAnEmptyFieldValueStopsTheResolution(): void
    {
        $object = $this->object(['sku' => '']);

        self::assertNull(
            $this->pathResolver->resolve('/{field:sku}', $object, $this->asset('a.jpg', 'image'))
        );
    }

    public function testANonScalarFieldValueStopsTheResolution(): void
    {
        $object = $this->object(['sku' => ['an', 'array']]);

        self::assertNull(
            $this->pathResolver->resolve('/{field:sku}', $object, $this->asset('a.jpg', 'image'))
        );
    }

    public function testAStringableFieldValueIsResolved(): void
    {
        $value = new class () {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        self::assertSame(
            '/stringable',
            $this->pathResolver->resolve('/{field:sku}', $this->object(['sku' => $value]), $this->asset('a.jpg', 'image'))
        );
    }

    public function testFieldTokenWithoutAFieldNameStopsTheResolution(): void
    {
        self::assertNull(
            $this->pathResolver->resolve('/{field}', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    public function testAnUnresolvedTokenIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('token "{field:sku}" of pattern "/{class}/{field:sku}"'));

        $resolver = new PathResolver($logger);

        self::assertNull(
            $resolver->resolve('/{class}/{field:sku}', $this->object(['sku' => null]), $this->asset('a.jpg', 'image'))
        );
    }

    public function testOnlyTheFirstUnresolvedTokenIsReported(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('token "{field:sku}"'));

        $resolver = new PathResolver($logger);

        $resolver->resolve('/{field:sku}/{field:ean}', $this->object(['sku' => null, 'ean' => null]), $this->asset('a.jpg', 'image'));
    }

    public function testResolvedSegmentsAreTurnedIntoValidAssetKeys(): void
    {
        $object = $this->object(['sku' => 'A?B*C|D"E']);

        self::assertSame(
            '/A-B-C-D-E',
            $this->pathResolver->resolve('/{field:sku}', $object, $this->asset('a.jpg', 'image'))
        );
    }

    public function testASlashInsideAResolvedValueCreatesAFolderLevel(): void
    {
        // sanitizing happens per segment after substitution, which is what makes {date:Y/m} nest
        self::assertSame(
            '/A/B',
            $this->pathResolver->resolve('/{field:sku}', $this->object(['sku' => 'A/B']), $this->asset('a.jpg', 'image'))
        );
    }

    public function testEmptySegmentsAreDropped(): void
    {
        self::assertSame(
            '/Product/42',
            $this->pathResolver->resolve('//{class}///{id}//', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    public function testAPatternWithoutTokensIsReturnedSanitized(): void
    {
        self::assertSame(
            '/products/images',
            $this->pathResolver->resolve('/products/images/', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    public function testAPatternThatSanitizesToNothingReturnsTheRoot(): void
    {
        self::assertSame(
            '/',
            $this->pathResolver->resolve('/...', $this->object(), $this->asset('a.jpg', 'image'))
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function object(array $fields = []): Concrete
    {
        $object = $this->createMock(Concrete::class);
        $object->method('getId')->willReturn(42);
        $object->method('getKey')->willReturn('my-product');
        $object->method('getClassName')->willReturn('Product');
        $object->method('get')->willReturnCallback(
            static fn (string $fieldName): mixed => $fields[$fieldName] ?? null
        );

        return $object;
    }

    private function asset(string $filename, string $type): Asset
    {
        $asset = $this->createMock(Asset::class);
        $asset->method('getId')->willReturn(100);
        $asset->method('getFilename')->willReturn($filename);
        $asset->method('getType')->willReturn($type);

        return $asset;
    }
}
