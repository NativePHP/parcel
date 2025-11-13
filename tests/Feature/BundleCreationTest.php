<?php

use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->tempWorkingDir = initTempDirectory();
});

afterEach(function () {
    // cleanupTempDirectory($this->tempWorkingDir);
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

    // Run parcel prepare with --force to ensure we get the latest stubs
    $prepareProcess = runProcessInTemp(['php', $parcelBin, 'prepare', '--force'], $projectDir);
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

test('can serve bundle via PHP built-in server and receive HTTP responses', function (string $repository) {
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

    // Run parcel prepare with --force to ensure we get the latest stubs
    $prepareProcess = runProcessInTemp(['php', $parcelBin, 'prepare', '--force'], $projectDir);
    expect($prepareProcess->isSuccessful())->toBeTrue('Parcel prepare failed: '.$prepareProcess->getErrorOutput());

    // Create .env file if it doesn't exist
    if (! $filesystem->exists($projectDir.'/.env') && $filesystem->exists($projectDir.'/.env.example')) {
        $filesystem->copy($projectDir.'/.env.example', $projectDir.'/.env');
    } elseif (! $filesystem->exists($projectDir.'/.env')) {
        $filesystem->dumpFile($projectDir.'/.env', '');
    }

    // Run composer install
    $composerProcess = runProcessInTemp(['composer', 'install', '--no-dev', '--no-interaction'], $projectDir);
    expect($composerProcess->isSuccessful())->toBeTrue('Composer install failed: '.$composerProcess->getErrorOutput());

    // Generate application key and compile
    runProcessInTemp(['php', 'artisan', 'key:generate'], $projectDir);
    $compileProcess = runProcessInTemp(['php', $parcelBin, 'compile'], $projectDir);

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

    // Prepare environment variables
    $env = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'LARAVEL_STORAGE_PATH' => $projectDir.'/storage',
        'NATIVEPHP_RUNNING' => 'true',
        'NATIVEPHP_STORAGE_PATH' => $projectDir.'/storage',
        'NATIVEPHP_DATABASE_PATH' => $projectDir.'/database/database.sqlite',
        'APP_SERVICES_CACHE' => $projectDir.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $projectDir.'/bootstrap/cache/packages.php',
        'APP_CONFIG_CACHE' => $projectDir.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $projectDir.'/bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $projectDir.'/bootstrap/cache/events.php',
    ];

    // Start PHP built-in server on random port (0 = let OS choose)
    $serverProcess = null;
    $port = null;

    try {
        [$serverProcess, $port] = startPhpServer('__nativephp_app_bundle', 0, $projectDir.'/build/', $env);

        expect($port)->toBeGreaterThan(0, 'Failed to determine server port');

        // Wait for server to be ready
        $serverReady = waitForServer('localhost', $port, 10);
        expect($serverReady)->toBeTrue("Server did not start within 10 seconds on port {$port}");

        // Test 1: Request root path and verify HTML response
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents("http://localhost:{$port}/", false, $context);
        expect($response)->not->toBeFalse('Failed to fetch root path');

        // Check we get an HTTP response (200 or 500 is fine - NativePHP needs Electron wrapper)
        $statusLine = $http_response_header[0] ?? '';
        expect($statusLine)->toMatch('/HTTP\/1\.[01] (200|500)/');

        // Check for HTML content (even error pages are HTML from Laravel/Symfony)
        expect($response)->toContain('<html');

        // Test 2: Try to request a static asset if one exists
        // Just verify the server responds to asset requests (200 or 404 is fine)
        $staticAssetResponse = @file_get_contents("http://localhost:{$port}/favicon.ico", false, $context);

        if ($staticAssetResponse !== false) {
            $assetStatusLine = $http_response_header[0] ?? '';
            // Asset request should get some HTTP response (not fail to connect)
            expect($assetStatusLine)->toMatch('/HTTP\/1\.[01] (200|404|500)/');
        }

    } finally {
        // Always stop the server
        if ($serverProcess !== null) {
            stopPhpServer($serverProcess);
        }
    }

})->with('repositories');
