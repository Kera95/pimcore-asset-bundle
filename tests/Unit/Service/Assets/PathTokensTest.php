<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\Service\Assets;

use Codeception\Test\Unit;
use ReflectionClass;
use Tsf\AssetBundle\Service\Assets\PathTokens;

final class PathTokensTest extends Unit
{
    public function testEveryTokenConstantIsDocumented(): void
    {
        $documented = PathTokens::getNames();

        $constants = [];

        foreach ((new ReflectionClass(PathTokens::class))->getReflectionConstants() as $constant) {
            if (!$constant->isPublic() || str_ends_with($constant->getName(), '_PATTERN')) {
                continue;
            }

            $constants[$constant->getName()] = $constant->getValue();
        }

        foreach ($constants as $name => $token) {
            self::assertContains($token, $documented, sprintf('Token constant %s is missing a definition.', $name));
        }

        self::assertCount(count($constants), $documented, 'Every documented token needs a matching constant.');
    }

    public function testGetNamesMatchesTheDefinitionKeys(): void
    {
        self::assertSame(array_keys(PathTokens::getDefinitions()), PathTokens::getNames());
    }

    public function testGetInfoLineListsEveryToken(): void
    {
        $infoLine = PathTokens::getInfoLine();

        foreach (PathTokens::getNames() as $name) {
            self::assertStringContainsString('{' . $name . '}', $infoLine);
        }
    }

    /**
     * @dataProvider knownTokenPatternProvider
     */
    public function testGetUnknownTokensAcceptsKnownTokens(string $pattern): void
    {
        self::assertSame([], PathTokens::getUnknownTokens($pattern));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function knownTokenPatternProvider(): array
    {
        return [
            'no token'            => ['/products'],
            'single token'        => ['/{class}'],
            'multiple tokens'     => ['/{class}/{char:1}/{char:2}'],
            'token with argument' => ['/{field:sku}/{date:Y/m}'],
            'token inside a text' => ['/prefix-{key}-suffix'],
        ];
    }

    public function testGetUnknownTokensReportsUnknownNamesOnce(): void
    {
        self::assertSame(
            ['nope', 'other'],
            PathTokens::getUnknownTokens('/{class}/{nope}/{other:1}/{nope}')
        );
    }

    /**
     * @dataProvider malformedPatternProvider
     */
    public function testHasMalformedToken(string $pattern, bool $expected): void
    {
        self::assertSame($expected, PathTokens::hasMalformedToken($pattern));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function malformedPatternProvider(): array
    {
        return [
            'well formed'          => ['/{class}/{char:1}', false],
            'no tokens at all'     => ['/products/images', false],
            'unclosed brace'       => ['/{class', true],
            'unopened brace'       => ['/class}', true],
            'empty braces'         => ['/{}', true],
            'uppercase token name' => ['/{Class}', true],
            'nested braces'        => ['/{{class}}', true],
        ];
    }

    public function testAMalformedTokenIsStillReportedAsUnknown(): void
    {
        self::assertTrue(PathTokens::hasMalformedToken('/{Class}'));
        self::assertSame(['Class'], PathTokens::getUnknownTokens('/{Class}'));
    }
}
