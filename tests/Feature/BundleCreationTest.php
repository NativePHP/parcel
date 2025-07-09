<?php

use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->tempWorkingDir = initTempDirectory();
});

afterEach(function () {
    // cleanupTempDirectory($this->tempWorkingDir);
    dump('Temp working directory: ', $this->tempWorkingDir);
});

dataset('repositories', [
    // 'relay' => ['https://github.com/gwleuverink/relay.git'],
    'kitchen-sink' => ['https://github.com/NativePHP/kitchen-sink.git'],
    // 'tailscale-notifier' => ['https://github.com/SRWieZ/tailscale-notifier.git'],
]);

test('can create a bundle of a NativePHP project', function (string $repository) {
    $filesystem = new Filesystem;

    // Extract repository name from URL
    $repoName = basename($repository, '.git');
    $projectDir = $this->tempWorkingDir.'/'.$repoName;

    // Clone the repository
    $cloneProcess = runProcessInTemp(['git', 'clone', '--depth', '1', $repository, $repoName]);
    expect($cloneProcess->isSuccessful())->toBeTrue('Failed to clone repository: '.$cloneProcess->getErrorOutput());

    // Get path to parcel binary
    $parcelBin = realpath(__DIR__.'/../../bin/parcel');
    expect($filesystem->exists($parcelBin))->toBeTrue('Parcel binary not found at: '.$parcelBin);

    // Run parcel prepare
    $prepareProcess = runProcessInTemp(['php', $parcelBin, 'prepare'], $projectDir);
    expect($prepareProcess->isSuccessful())->toBeTrue('Parcel prepare failed: '.$prepareProcess->getErrorOutput());

    // Create .env file if it doesn't exist (required by box.json)
    if (! $filesystem->exists($projectDir.'/.env') && $filesystem->exists($projectDir.'/.env.example')) {
        $filesystem->copy($projectDir.'/.env.example', $projectDir.'/.env');
    } elseif (! $filesystem->exists($projectDir.'/.env')) {
        $filesystem->dumpFile($projectDir.'/.env', '');
    }

    // Run composer install to ensure dependencies are available
    $composerProcess = runProcessInTemp(['composer', 'install', '--no-dev', '--no-interaction'], $projectDir);
    expect($composerProcess->isSuccessful())->toBeTrue('Composer install failed: '.$composerProcess->getErrorOutput());

    // Run parcel compile
    runProcessInTemp(['php', 'artisan', 'key:generate'], $projectDir);
    $compileProcess = runProcessInTemp(['php', $parcelBin, 'compile'], $projectDir);

    // If compile fails, provide detailed output
    if (! $compileProcess->isSuccessful()) {
        $errorOutput = "Parcel compile failed:\n";
        $errorOutput .= 'Exit code: '.$compileProcess->getExitCode()."\n";
        $errorOutput .= 'Error output: '.$compileProcess->getErrorOutput()."\n";
        $errorOutput .= 'Standard output: '.$compileProcess->getOutput()."\n";
        $this->fail($errorOutput);
    }

    $bundleFile = $projectDir.'/build/__nativephp_app_bundle';
    expect($filesystem->exists($bundleFile))->toBeTrue('Bundle file not found: '.$bundleFile);

    // Create necessary directories for NativePHP runtime
    $filesystem->mkdir($projectDir.'/storage/logs');
    $filesystem->mkdir($projectDir.'/storage/framework/cache');
    $filesystem->mkdir($projectDir.'/storage/framework/sessions');
    $filesystem->mkdir($projectDir.'/storage/framework/views');
    $filesystem->mkdir($projectDir.'/storage/framework/testing');
    $filesystem->mkdir($projectDir.'/bootstrap/cache');
    $filesystem->mkdir($projectDir.'/database');
    $filesystem->dumpFile($projectDir.'/database/database.sqlite', '');

    // Prepare environment variables similar to NativePHP
    $env = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'LARAVEL_STORAGE_PATH' => $projectDir.'/storage',
        'NATIVEPHP_RUNNING' => 'true',
        'NATIVEPHP_STORAGE_PATH' => $projectDir.'/storage',
        'NATIVEPHP_DATABASE_PATH' => $projectDir.'/database/database.sqlite',
        // Cache paths for production mode
        'APP_SERVICES_CACHE' => $projectDir.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $projectDir.'/bootstrap/cache/packages.php',
        'APP_CONFIG_CACHE' => $projectDir.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $projectDir.'/bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $projectDir.'/bootstrap/cache/events.php',
    ];

    // Test that the bundle can execute artisan commands
    // php artisan --version doesn't really test the app. But test the basic router functionality
    $artisanProcess = runNativePhpCommand(['__nativephp_app_bundle', 'artisan', '--version'], $projectDir.'/build/', $env);
    expect($artisanProcess->isSuccessful())->toBeTrue('Failed to run artisan list: '.$artisanProcess->getErrorOutput());
    expect($artisanProcess->getOutput())->toContain('Laravel');

})->with('repositories');

// TODO: test artisan serve command and try to access the welcome page or the /up page for laravel 12 for example.
// TODO: test to see if the app create the database.sqlite file in the database directory
