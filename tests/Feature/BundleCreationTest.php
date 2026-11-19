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

    // Create app data directory for NativePHP runtime
    $appDataDir = $this->tempWorkingDir.'/app-data';
    $filesystem->mkdir($appDataDir);

    // nativephp-desktop sets ONE variable and derives the rest from it
    $appDir = $appDataDir.'/app';
    $env = [
        'NATIVEPHP_APP_DATA_PATH' => $appDataDir,
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'NATIVEPHP_RUNNING' => 'true',
        // Derived from NATIVEPHP_APP_DATA_PATH
        'LARAVEL_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_DATABASE_PATH' => $appDir.'/database/database.sqlite',
        'APP_SERVICES_CACHE' => $appDir.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $appDir.'/bootstrap/cache/packages.php',
        'APP_CONFIG_CACHE' => $appDir.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $appDir.'/bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $appDir.'/bootstrap/cache/events.php',
    ];

    // Test that the bundle can execute artisan commands
    // php artisan --version doesn't really test the app. But test the basic router functionality
    $artisanProcess = runNativePhpCommand(['__nativephp_app_bundle', 'artisan', '--version'], $projectDir.'/build/', $env);
    expect($artisanProcess->isSuccessful())->toBeTrue('Failed to run artisan list: '.$artisanProcess->getErrorOutput());
    expect($artisanProcess->getOutput())->toContain('Laravel');

})->with('repositories');

test('can optimize laravel outside the phar', function (string $repository) {
    // Note: We test optimization outside the PHAR because Laravel checks if the
    // bootstrap/cache directory is writable. This verifies that Phar::mount()
    // successfully redirects writes to the external filesystem.

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

    // Create app data directory for NativePHP runtime
    $appDataDir = $this->tempWorkingDir.'/app-data';
    $filesystem->mkdir($appDataDir);

    // nativephp-desktop sets ONE variable and derives the rest from it
    $appDir = $appDataDir.'/app';
    $env = [
        'NATIVEPHP_APP_DATA_PATH' => $appDataDir,
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'NATIVEPHP_RUNNING' => 'true',
        // Derived from NATIVEPHP_APP_DATA_PATH
        'LARAVEL_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_DATABASE_PATH' => $appDir.'/database/database.sqlite',
        'APP_SERVICES_CACHE' => $appDir.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $appDir.'/bootstrap/cache/packages.php',
        'APP_CONFIG_CACHE' => $appDir.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $appDir.'/bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $appDir.'/bootstrap/cache/events.php',
    ];

    // Test artisan optimize creates cache files in external mounted directory
    $optimizeProcess = runNativePhpCommand(['__nativephp_app_bundle', 'artisan', 'optimize'], $projectDir.'/build/', $env);
    expect($optimizeProcess->isSuccessful())->toBeTrue('Failed to run artisan optimize: '.$optimizeProcess->getErrorOutput());

    // Verify cache files were created in external directory (not in PHAR)
    $bootstrapCacheDir = $appDir.'/bootstrap/cache';
    expect($filesystem->exists($bootstrapCacheDir.'/config.php'))->toBeTrue('config.php cache not created');
    expect($filesystem->exists($bootstrapCacheDir.'/routes-v7.php'))->toBeTrue('routes cache not created');
    expect($filesystem->exists($bootstrapCacheDir.'/events.php'))->toBeTrue('events cache not created');

})->with('repositories');

test('can bundle pre-optimized laravel application', function (string $repository) {
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

    // Generate key and optimize BEFORE compiling the PHAR
    runProcessInTemp(['php', 'artisan', 'key:generate'], $projectDir);
    $optimizeProcess = runProcessInTemp(['php', 'artisan', 'optimize'], $projectDir);
    expect($optimizeProcess->isSuccessful())->toBeTrue('Artisan optimize failed: '.$optimizeProcess->getErrorOutput());

    // Verify cache files exist before bundling
    expect($filesystem->exists($projectDir.'/bootstrap/cache/config.php'))->toBeTrue('config.php not cached before bundling');
    expect($filesystem->exists($projectDir.'/bootstrap/cache/routes-v7.php'))->toBeTrue('routes not cached before bundling');
    expect($filesystem->exists($projectDir.'/bootstrap/cache/events.php'))->toBeTrue('events not cached before bundling');

    // Run parcel compile with optimized caches
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

    // Create app data directory for NativePHP runtime
    $appDataDir = $this->tempWorkingDir.'/app-data-preoptimized';
    $filesystem->mkdir($appDataDir);

    // nativephp-desktop sets ONE variable and derives the rest from it
    $appDir = $appDataDir.'/app';
    $env = [
        'NATIVEPHP_APP_DATA_PATH' => $appDataDir,
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'NATIVEPHP_RUNNING' => 'true',
        // Derived from NATIVEPHP_APP_DATA_PATH
        'LARAVEL_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_DATABASE_PATH' => $appDir.'/database/database.sqlite',
        'APP_SERVICES_CACHE' => $appDir.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $appDir.'/bootstrap/cache/packages.php',
        'APP_CONFIG_CACHE' => $appDir.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $appDir.'/bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $appDir.'/bootstrap/cache/events.php',
        // Prevent artisan about from trying to detect composer
        'COMPOSER_BINARY' => 'false',
    ];

    // Verify cached files are accessible (they should be in the PHAR or mounted directory)
    // Run a command that would fail if config cache doesn't work
    $configShowProcess = runNativePhpCommand(['__nativephp_app_bundle', 'artisan', 'config:show', 'app.name'], $projectDir.'/build/', $env);
    expect($configShowProcess->isSuccessful())->toBeTrue('Config cache test failed: '.$configShowProcess->getErrorOutput());

    // Run a command that would fail if routes cache doesn't work
    $routeListProcess = runNativePhpCommand(['__nativephp_app_bundle', 'artisan', 'route:list', '--json'], $projectDir.'/build/', $env);
    expect($routeListProcess->isSuccessful())->toBeTrue('Routes cache test failed: '.$routeListProcess->getErrorOutput());

    // Parse route list to verify routes are loaded from cache
    $routes = json_decode($routeListProcess->getOutput(), true);
    expect($routes)->toBeArray('Routes not returned as JSON array');
    expect(count($routes))->toBeGreaterThan(0, 'No routes found - cache may not be working');

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

    // Create app data directory for NativePHP runtime
    $appDataDir = $this->tempWorkingDir.'/app-data-http';
    $filesystem->mkdir($appDataDir);

    // nativephp-desktop sets ONE variable and derives the rest from it
    $appDir = $appDataDir.'/app';
    $env = [
        'NATIVEPHP_APP_DATA_PATH' => $appDataDir,
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'NATIVEPHP_RUNNING' => 'true',
        // Derived from NATIVEPHP_APP_DATA_PATH
        'LARAVEL_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_STORAGE_PATH' => $appDir.'/storage',
        'NATIVEPHP_DATABASE_PATH' => $appDir.'/database/database.sqlite',
        'APP_SERVICES_CACHE' => $appDir.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $appDir.'/bootstrap/cache/packages.php',
        'APP_CONFIG_CACHE' => $appDir.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $appDir.'/bootstrap/cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $appDir.'/bootstrap/cache/events.php',
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
