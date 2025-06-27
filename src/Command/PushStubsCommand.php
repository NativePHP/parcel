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
        $this->filesystem = new Filesystem();
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

        if (!$this->filesystem->exists($targetDir)) {
            $io->error("Target directory does not exist: {$targetDir}");
            return Command::FAILURE;
        }

        // Check if it's a Laravel project
        if (!$this->filesystem->exists(Path::join($targetDir, 'artisan'))) {
            $io->warning("Target directory doesn't appear to be a Laravel project (missing artisan file)");
            if (!$io->confirm('Continue anyway?', false)) {
                return Command::FAILURE;
            }
        }

        $stubsDir = $this->getStubsDirectory();
        $copiedFiles = [];

        // Copy individual stub files
        $stubFiles = [
            'box-helper.php',
            'phar-router.php',
            'serverless.php'
        ];

        foreach ($stubFiles as $stubFile) {
            $source = Path::join($stubsDir, $stubFile);
            $target = Path::join($targetDir, $stubFile);

            if ($this->filesystem->exists($target) && !$force) {
                $io->note("Skipping {$stubFile} (already exists, use --force to overwrite)");
                continue;
            }

            $this->filesystem->copy($source, $target);
            $copiedFiles[] = $stubFile;
            $io->text("✓ Copied {$stubFile}");
        }

        // Copy App/Native directory if it exists
        $nativeDir = Path::join($stubsDir, 'App', 'Native');
        if ($this->filesystem->exists($nativeDir)) {
            $targetNativeDir = Path::join($targetDir, 'app', 'Native');
            
            if (!$this->filesystem->exists($targetNativeDir) || $force) {
                $this->filesystem->mirror($nativeDir, $targetNativeDir);
                $copiedFiles[] = 'app/Native/';
                $io->text("✓ Copied app/Native/ directory");
            } else {
                $io->note("Skipping app/Native/ directory (already exists, use --force to overwrite)");
            }
        }

        // Handle box.json configuration
        $this->handleBoxJson($targetDir, $force, $io);

        if (empty($copiedFiles)) {
            $io->info('No files were copied (all files already exist, use --force to overwrite)');
        } else {
            $io->success(sprintf('Successfully pushed %d file(s) to %s', count($copiedFiles), $targetDir));
        }

        $io->note([
            'Next steps:',
            '1. Review and customize the box.json configuration',
            '2. Run: parcel compile',
            '3. Your PHAR will be created in the build/ directory'
        ]);

        return Command::SUCCESS;
    }

    private function getStubsDirectory(): string
    {
        // Get the directory where this package is installed
        $reflection = new \ReflectionClass($this);
        $packageDir = dirname(dirname($reflection->getFileName()));
        
        return Path::join($packageDir, '..', 'stubs');
    }

    private function handleBoxJson(string $targetDir, bool $force, SymfonyStyle $io): void
    {
        $boxJsonPath = Path::join($targetDir, 'box.json');
        $stubBoxJsonPath = Path::join($this->getStubsDirectory(), 'box.json');

        if ($this->filesystem->exists($boxJsonPath) && !$force) {
            $io->note('Skipping box.json (already exists, use --force to overwrite)');
            return;
        }

        if (!$this->filesystem->exists($stubBoxJsonPath)) {
            $io->warning('Stub box.json not found, creating basic configuration');
            $this->createBasicBoxJson($boxJsonPath);
        } else {
            // Copy and customize the box.json
            $boxJsonContent = file_get_contents($stubBoxJsonPath);
            
            // Replace placeholder values with project-specific ones
            $projectName = basename($targetDir);
            $boxJsonContent = str_replace(
                '"output": "build/__nativephp_app_bundle"',
                '"output": "build/' . $projectName . '.phar"',
                $boxJsonContent
            );

            // Add RealPathMod compactor to the configuration
            $boxJson = json_decode($boxJsonContent, true);
            if (!in_array('Parcel\\Compactor\\RealPathMod', $boxJson['compactors'] ?? [])) {
                $boxJson['compactors'][] = 'Parcel\\Compactor\\RealPathMod';
            }

            $this->filesystem->dumpFile($boxJsonPath, json_encode($boxJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $io->text("✓ Created box.json configuration");
    }

    private function createBasicBoxJson(string $path): void
    {
        $projectName = basename(dirname($path));
        $basicConfig = [
            'chmod' => '0700',
            'shebang' => false,
            'main' => 'public/index.php',
            'banner' => [
                'This is a NativePHP bundle',
                'https://nativephp.com/'
            ],
            'directories' => ['app', 'bootstrap', 'config', 'database', 'resources/views', 'routes'],
            'directories-bin' => ['public', 'resources/css', 'resources/js'],
            'files-bin' => ['artisan', '.env', 'composer.json', 'phar-router.php', 'box-helper.php'],
            'finder' => [
                [
                    'notName' => ['LICENSE', '*.md', '*.dist', 'Makefile', 'composer.json', 'composer.lock'],
                    'exclude' => [
                        'doc', 'docs', 'test', 'test_old', 'tests', 'Tests', 'bin',
                        'nativephp/php-bin', 'nativephp/electron/resources'
                    ],
                    'in' => 'vendor'
                ],
                [
                    'exclude' => ['logs', 'app/livewire-tmp', 'framework/sessions'],
                    'in' => 'storage'
                ],
                [
                    'notName' => '*.sqlite',
                    'in' => 'database'
                ]
            ],
            'output' => "build/{$projectName}.phar",
            'compression' => 'GZ',
            'compactors' => [
                'KevinGH\\Box\\Compactor\\Php',
                'KevinGH\\Box\\Compactor\\Json',
                'Parcel\\Compactor\\RealPathMod'
            ],
            'exclude-dev-files' => false,
            'exclude-composer-files' => false
        ];

        $this->filesystem->dumpFile($path, json_encode($basicConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}