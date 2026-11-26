<?php

declare(strict_types=1);

namespace Parcel\Command;

use stdClass;
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

        // Decode to objects, not arrays. An array can't tell an empty object
        // from an empty list, so "autoload-dev": {} would come back as [] and
        // Composer refuses to dump the autoloader for it.
        $json = json_decode(file_get_contents($composerJsonPath));
        if (! $json instanceof stdClass) {
            $io->error('Invalid composer.json file');

            return Command::FAILURE;
        }

        $autoload = (object) ($json->autoload ?? new stdClass);
        $autoload->files = [...($autoload->files ?? []), 'box-helper.php'];
        $json->autoload = $autoload;

        $scripts = (object) ($json->scripts ?? new stdClass);
        $postAutoloadDump = (array) ($scripts->{'post-autoload-dump'} ?? []);
        $scripts->{'post-autoload-dump'} = array_values(array_filter(
            $postAutoloadDump,
            fn ($script) => ! str_contains($script, 'bin/testbench'),
        ));
        $json->scripts = $scripts;

        file_put_contents($composerJsonPath, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
