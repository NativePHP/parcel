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

function startPhpServer(string $pharPath, int $port, string $cwd, array $env = []): array
{
    $process = new Process(
        ['php', '-S', "localhost:{$port}", $pharPath],
        $cwd,
        $env
    );
    $process->setTimeout(null); // No timeout for server process
    $process->start();

    // If port is 0, PHP will choose a random port - we need to extract it from output
    $actualPort = $port;
    if ($port === 0) {
        // Wait a bit for the server to output its startup message
        usleep(200000); // 200ms

        // Parse the server output to get the actual port
        // Output format: "PHP 8.x.x Development Server (http://localhost:XXXXX) started"
        $output = $process->getErrorOutput();
        if (preg_match('/localhost:(\d+)/', $output, $matches)) {
            $actualPort = (int) $matches[1];
        }
    }

    return [$process, $actualPort];
}

function waitForServer(string $host, int $port, int $timeout = 5): bool
{
    $start = time();
    while (time() - $start < $timeout) {
        // Set a custom error handler that completely ignores warnings
        $previousHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Completely ignore the error, return true to prevent PHP's internal error handler
            return true;
        }, E_WARNING | E_NOTICE);

        $connection = stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            1,
            STREAM_CLIENT_CONNECT
        );

        // Restore the previous error handler
        restore_error_handler();

        if ($connection !== false) {
            fclose($connection);

            return true;
        }
        usleep(100000); // Wait 100ms before retrying
    }

    return false;
}

function stopPhpServer(Process $process): void
{
    if ($process->isRunning()) {
        $process->stop(3, SIGTERM);
    }
}
