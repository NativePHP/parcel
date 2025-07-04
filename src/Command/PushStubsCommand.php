<?php

declare(strict_types=1);

namespace Parcel\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'push-stubs',
    description: 'Push NativePHP stubs and configuration to a Laravel project'
)]
class PushStubsCommand extends Command
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
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files')
            ->setHelp('This command copies NativePHP stubs and creates box.json configuration for a Laravel project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetDir = $input->getArgument('target');
        $force = $input->getOption('force');

        // Resolve target directory
        $targetDir = Path::makeAbsolute($targetDir, getcwd());

        if (! $this->filesystem->exists($targetDir)) {
            $io->error("Target directory does not exist: {$targetDir}");

            return Command::FAILURE;
        }

        if (! $this->filesystem->exists(Path::join($targetDir, 'artisan'))) {
            $io->error("Target directory doesn't appear to be a Laravel project (missing artisan file)");

            return Command::FAILURE;
        }

        $stubsDir = $this->getStubsDirectory();

        // Copy individual stub files
        $stubFiles = [
            'box.json',
            'box-helper.php',
            'phar-router.php',
            'serverless.php',
        ];

        foreach ($stubFiles as $stubFile) {
            $source = Path::join($stubsDir, $stubFile);
            $target = Path::join($targetDir, $stubFile);

            if (! $this->filesystem->exists($source)) {
                continue;
            }

            if ($this->filesystem->exists($target) && ! $force) {
                continue;
            }

            $this->filesystem->copy($source, $target);
        }

        // $nativeDir = Path::join($stubsDir, 'App', 'Native');
        // if ($this->filesystem->exists($nativeDir)) {
        //     $targetNativeDir = Path::join($targetDir, 'app', 'Native');
        //
        //     if (!$this->filesystem->exists($targetNativeDir) || $force) {
        //         $this->filesystem->mirror($nativeDir, $targetNativeDir);
        //     }
        // }

        return Command::SUCCESS;
    }

    private function getStubsDirectory(): string
    {
        // Try multiple approaches to find the stubs directory
        $possiblePaths = [
            // Local development or installation
            __DIR__.'/../../stubs',

            // Global installation via composer
            dirname(__DIR__, 3).'/nativephp/parcel/stubs',

            // Alternative global installation path
            dirname(__DIR__, 4).'/stubs',

            // Vendor installation
            dirname(__DIR__, 2).'/stubs',
        ];

        foreach ($possiblePaths as $path) {
            $realPath = realpath($path);
            if ($realPath && is_dir($realPath) && $this->filesystem->exists(Path::join($realPath, 'box.json'))) {
                return $realPath;
            }
        }

        // Default fallback to local development path
        return __DIR__.'/../../stubs';
    }
}
