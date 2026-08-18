<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tsf\AssetBundle\Service\Assets\PathTokens;

#[AsCommand(name: 'tsf:asset:path-tokens', description: 'Lists the tokens available in the tsf_asset sorting path patterns')]
final class ListPathTokensCommand extends Command
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];

        foreach (PathTokens::getDefinitions() as $token => $description) {
            $rows[] = ['{' . $token . '}', $description];
        }

        $io->title('Available path tokens');
        $io->table(['Token', 'Description'], $rows);

        return Command::SUCCESS;
    }
}
