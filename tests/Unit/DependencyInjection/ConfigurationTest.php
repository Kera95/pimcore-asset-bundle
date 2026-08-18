<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\DependencyInjection;

use Codeception\Test\Unit;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Tsf\AssetBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends Unit
{
    public function testTheEmptyConfigurationYieldsTheDocumentedDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(
            [
                'sorting' => [
                    'data_objects' => [
                        'enabled' => true,
                        'path' => Configuration::DEFAULT_OBJECT_PATH,
                        'asset_types' => [],
                        'classes' => [],
                    ],
                    'documents' => [
                        'enabled' => false,
                        'path' => Configuration::DEFAULT_DOCUMENT_PATH,
                        'asset_types' => [],
                    ],
                ],
            ],
            $config
        );
    }

    public function testTheDefaultPathsAreValidPatterns(): void
    {
        $config = $this->process([
            'sorting' => [
                'data_objects' => ['path' => Configuration::DEFAULT_OBJECT_PATH],
                'documents' => ['path' => Configuration::DEFAULT_DOCUMENT_PATH],
            ],
        ]);

        self::assertSame(Configuration::DEFAULT_OBJECT_PATH, $config['sorting']['data_objects']['path']);
        self::assertSame(Configuration::DEFAULT_DOCUMENT_PATH, $config['sorting']['documents']['path']);
    }

    public function testClassOverridesAreKeyedByClassNameAndDefaultToEnabled(): void
    {
        $config = $this->process([
            'sorting' => [
                'data_objects' => [
                    'classes' => [
                        'Product' => ['path' => '/products'],
                    ],
                ],
            ],
        ]);

        self::assertEquals(
            ['Product' => ['enabled' => true, 'path' => '/products', 'asset_types' => []]],
            $config['sorting']['data_objects']['classes']
        );
    }

    public function testAClassOverrideWithoutAPathKeepsANullPath(): void
    {
        $config = $this->process([
            'sorting' => ['data_objects' => ['classes' => ['Product' => ['enabled' => false]]]],
        ]);

        self::assertNull($config['sorting']['data_objects']['classes']['Product']['path']);
    }

    public function testLaterConfigurationFilesOverrideEarlierOnes(): void
    {
        $config = $this->process(
            ['sorting' => ['data_objects' => ['path' => '/first']]],
            ['sorting' => ['data_objects' => ['enabled' => false, 'path' => '/second']]]
        );

        self::assertFalse($config['sorting']['data_objects']['enabled']);
        self::assertSame('/second', $config['sorting']['data_objects']['path']);
    }

    /**
     * @dataProvider invalidPathProvider
     */
    public function testInvalidPathsAreRejected(string $path, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->process(['sorting' => ['data_objects' => ['path' => $path]]]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidPathProvider(): array
    {
        return [
            'empty'        => ['', 'The path pattern cannot be empty.'],
            'relative'     => ['products/{class}', 'must be absolute and start with a "/"'],
            'malformed'    => ['/{class', 'contains a malformed token'],
            'unknown'      => ['/{nope}', 'uses the unknown token "{nope}"'],
            'unknown case' => ['/{Class}', 'contains a malformed token'],
        ];
    }

    public function testInvalidPathsAreRejectedInsideAClassOverride(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('uses the unknown token "{nope}"');

        $this->process([
            'sorting' => ['data_objects' => ['classes' => ['Product' => ['path' => '/{nope}']]]],
        ]);
    }

    public function testInvalidPathsAreRejectedInsideAnAssetTypeOverride(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be absolute and start with a "/"');

        $this->process([
            'sorting' => ['data_objects' => ['asset_types' => ['image' => 'images']]],
        ]);
    }

    public function testInvalidPathsAreRejectedInsideTheDocumentSection(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('contains a malformed token');

        $this->process(['sorting' => ['documents' => ['path' => '/documents/{doctype']]]);
    }

    public function testTheUnknownTokenMessageListsTheAvailableTokens(): void
    {
        try {
            $this->process(['sorting' => ['data_objects' => ['path' => '/{nope}']]]);
        } catch (InvalidConfigurationException $exception) {
            self::assertStringContainsString('Available tokens: id, key, type', $exception->getMessage());

            return;
        }

        self::fail(sprintf('Expected a %s to be thrown.', InvalidConfigurationException::class));
    }

    /**
     * @param array<string, mixed> ...$configs
     * @return array<string, mixed>
     */
    private function process(array ...$configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
