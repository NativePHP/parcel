<?php

declare(strict_types=1);

namespace Parcel\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'prepare',
    description: 'Prepare Laravel project for PHAR compilation by pushing stubs and patching composer.json'
)]
class PrepareCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'Target Laravel project directory', '.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument('target');
        $force = $input->getOption('force');

        // Execute push-stubs command
        $pushStubsCommand = $this->getApplication()->find('push-stubs');
        $pushStubsInput = new ArrayInput([
            'target' => $target,
            '--force' => $force,
        ]);
        $result = $pushStubsCommand->run($pushStubsInput, $output);

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        // Execute patch-composer command
        $patchComposerCommand = $this->getApplication()->find('patch-composer');
        $patchComposerInput = new ArrayInput([
            'target' => $target,
        ]);

        return $patchComposerCommand->run($patchComposerInput, $output);
    }
}
