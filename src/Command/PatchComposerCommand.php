<?php

declare(strict_types=1);

namespace Parcel\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'patch-composer',
    description: 'Patch composer.json for NativePHP PHAR compilation'
)]
class PatchComposerCommand extends Command
{
    private Filesystem $filesystem;

    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new Filesystem;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'Target Laravel project directory', '.')
            ->setHelp('This command patches composer.json to prepare it for NativePHP PHAR compilation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetDir = $input->getArgument('target');

        $targetDir = Path::makeAbsolute($targetDir, getcwd());

        if (! $this->filesystem->exists($targetDir)) {
            $io->error("Target directory does not exist: {$targetDir}");

            return Command::FAILURE;
        }

        $composerJsonPath = Path::join($targetDir, 'composer.json');
        if (! $this->filesystem->exists($composerJsonPath)) {
            $io->error('composer.json not found in target directory');

            return Command::FAILURE;
        }

        $json = json_decode(file_get_contents($composerJsonPath), true);
        if (! $json) {
            $io->error('Invalid composer.json file');

            return Command::FAILURE;
        }

        $autoloadFiles = $json['autoload']['files'] ?? [];
        $autoloadFiles[] = 'box-helper.php';
        $json['autoload']['files'] = $autoloadFiles;

        $postAutoloadDump = $json['scripts']['post-autoload-dump'] ?? [];
        foreach ($postAutoloadDump as $key => $value) {
            if (str_contains($value, 'bin/testbench')) {
                unset($postAutoloadDump[$key]);
            }
        }
        $json['scripts']['post-autoload-dump'] = array_values($postAutoloadDump);

        file_put_contents($composerJsonPath, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
