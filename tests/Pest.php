<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

function initTempDirectory(): string
{
    $filesystem = new Filesystem;
    $tempDir = sys_get_temp_dir().'/parcel-test-'.uniqid();
    $filesystem->mkdir($tempDir);

    return $tempDir;
}

function cleanupTempDirectory(string $tempDir): void
{
    if (! is_dir($tempDir)) {
        return;
    }

    $tempDir = str_replace('/', DIRECTORY_SEPARATOR, $tempDir);

    if (PHP_OS_FAMILY === 'Windows') {
        // Use PowerShell Remove-Item with Force - most effective for locked files
        $process = new Process([
            'powershell', '-Command',
            "Remove-Item -Path '$tempDir' -Recurse -Force -ErrorAction SilentlyContinue",
        ]);
        $process->setTimeout(30);
        $process->run();

        // If PowerShell fails, try standard rmdir as backup
        if (! $process->isSuccessful() && is_dir($tempDir)) {
            $process = new Process(['cmd', '/c', 'rmdir', '/s', '/q', $tempDir]);
            $process->run();
        }

        // Final check and warning
        if (is_dir($tempDir)) {
            echo 'Warning: Could not clean up temp directory: '.$tempDir."\n";
        }
    } else {
        $process = new Process(['rm', '-rf', $tempDir]);
        $process->run();

        if (! $process->isSuccessful()) {
            echo 'Warning: Could not clean up temp directory: '.$tempDir."\n";
        }
    }
}

function runProcessInTemp(array $args, $cwd = null): Process
{
    $cwd = $cwd ?: test()->tempWorkingDir;
    $process = new Process($args, $cwd);
    $process->setTimeout(300); // 5 minutes timeout
    $process->run();

    return $process;
}

function runNativePhpCommand(array $command, string $cwd, array $env = []): Process
{
    // Prepend PHP with ini settings like NativePHP does
    $phpCommand = ['php', '-d', 'memory_limit=512M'];
    $phpCommand = array_merge($phpCommand, $command);

    $process = new Process($phpCommand, $cwd);
    $process->setTimeout(300); // 5 minutes timeout
    $process->setEnv($env);
    $process->run();

    return $process;
}
