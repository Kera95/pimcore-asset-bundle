<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\Command;

use Codeception\Test\Unit;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tsf\AssetBundle\Command\ListPathTokensCommand;
use Tsf\AssetBundle\Service\Assets\PathTokens;

final class ListPathTokensCommandTest extends Unit
{
    private CommandTester $commandTester;

    protected function _before(): void
    {
        $this->commandTester = new CommandTester(new ListPathTokensCommand());
    }

    public function testItSucceeds(): void
    {
        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
    }

    public function testItPrintsEveryTokenWithItsDescription(): void
    {
        $this->commandTester->execute([]);

        $display = $this->commandTester->getDisplay();

        foreach (PathTokens::getDefinitions() as $token => $description) {
            self::assertStringContainsString('{' . $token . '}', $display);
            // the table wraps long descriptions, so only the leading words are guaranteed to be intact
            self::assertStringContainsString(implode(' ', array_slice(explode(' ', $description), 0, 3)), $display);
        }
    }

    public function testItIsRegisteredUnderTheDocumentedName(): void
    {
        $attributes = (new ReflectionClass(ListPathTokensCommand::class))->getAttributes(AsCommand::class);

        self::assertCount(1, $attributes);

        $asCommand = $attributes[0]->newInstance();

        self::assertSame('tsf:asset:path-tokens', $asCommand->name);
        self::assertNotSame('', (string) $asCommand->description);
    }
}
