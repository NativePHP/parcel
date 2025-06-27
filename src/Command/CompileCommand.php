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
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'compile',
    description: 'Compile Laravel project into a PHAR using Box'
)]
class CompileCommand extends Command
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
            ->addArgument('working-dir', InputArgument::OPTIONAL, 'Working directory for compilation', '.')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to box.json configuration file')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug output')
            ->setHelp('This command compiles a Laravel project into a PHAR using Box with NativePHP enhancements.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workingDir = $input->getArgument('working-dir');
        $configFile = $input->getOption('config');

        // Resolve working directory
        $workingDir = Path::makeAbsolute($workingDir, getcwd());

        if (!$this->filesystem->exists($workingDir)) {
            $io->error("Working directory does not exist: {$workingDir}");
            return Command::FAILURE;
        }

        // Determine config file path
        if ($configFile) {
            $configFile = Path::makeAbsolute($configFile, $workingDir);
        } else {
            // Look for box.json in working directory
            $configFile = Path::join($workingDir, 'box.json');
            if (!$this->filesystem->exists($configFile)) {
                $configFile = Path::join($workingDir, 'box.json.dist');
            }
        }

        if (!$this->filesystem->exists($configFile)) {
            $io->error("No box.json configuration found.");
            return Command::FAILURE;
        }

        if (!$this->filesystem->exists(Path::join($workingDir, 'composer.json'))) {
            $io->error("No composer.json found in the working directory");
            return Command::FAILURE;
        }

        // Use Box from vendor directory
        $boxBinary = $this->resolveBoxBinary($workingDir);
        if (!$this->filesystem->exists($boxBinary)) {
            $io->error('Box binary not found at ./vendor/bin/box.');
            return Command::FAILURE;
        }

        $io->text("Using Box binary: {$boxBinary}");
        $io->text("Using configuration: {$configFile}");
        $io->text("Working directory: {$workingDir}");

        // Prepare Box command arguments
        $boxArgs = [$boxBinary, 'compile', '--config=' . $configFile];

        // Add options
        if ($input->getOption('debug')) {
            $boxArgs[] = '--debug';
        }

        // Add verbosity
        if ($output->isVerbose()) {
            $boxArgs[] = '-v';
        } elseif ($output->isVeryVerbose()) {
            $boxArgs[] = '-vv';
        } elseif ($output->isDebug()) {
            $boxArgs[] = '-vvv';
        }

        $io->section('Starting PHAR compilation with Box');

        // Execute Box compilation
        $process = new Process($boxArgs, $workingDir);
        $process->setTimeout(300); // 5 minutes timeout

        $exitCode = $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($exitCode === 0) {
            $io->success('PHAR compilation completed successfully!');
            $this->showOutputInfo($configFile, $workingDir, $io);
        } else {
            $io->error('PHAR compilation failed!');
            
            $errorOutput = $process->getErrorOutput();
            if (!empty($errorOutput)) {
                $io->text($errorOutput);
            }
        }

        return $exitCode;
    }


    private function showOutputInfo(string $configFile, string $workingDir, SymfonyStyle $io): void
    {
        $config = json_decode(file_get_contents($configFile), true);
        $outputPath = $config['output'] ?? null;
        
        if ($outputPath) {
            $fullOutputPath = Path::isAbsolute($outputPath) 
                ? $outputPath 
                : Path::join($workingDir, $outputPath);
            
            if ($this->filesystem->exists($fullOutputPath)) {
                $fileSize = filesize($fullOutputPath);
                $io->definitionList(
                    ['Output file' => $fullOutputPath],
                    ['File size' => $this->formatBytes($fileSize)]
                );
            }
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        
        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }

    private function resolveBoxBinary(string $workingDir): string
    {
        // When in local development, prefer the vendor/bin/box binary
        $localBoxBinary = Path::join($workingDir, 'vendor/bin/box');
        if ($this->filesystem->exists($localBoxBinary)) {
            return $localBoxBinary;
        }

        // Find Box in global Parcel installation
        $globalBoxBinary = Path::join(dirname(__DIR__, 2), 'vendor', 'bin', 'box');
        if ($this->filesystem->exists($globalBoxBinary)) {
            return $globalBoxBinary;
        }

        // Fallback to PATH-based discovery
        $command = PHP_OS_FAMILY === 'Windows' ? ['where', 'box'] : ['which', 'box'];
        $process = new Process($command);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output = trim($process->getOutput());
            if (!empty($output)) {
                $lines = explode("\n", $output);
                return trim($lines[0]);
            }
        }

        return Path::join($workingDir, 'vendor', 'bin', 'box');
    }
}