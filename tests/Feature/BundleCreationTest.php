<?php

use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    initTempDirectory();
});

afterEach(function () {
    clearTempDirectory();
});

dataset('repositories', [
    'tailscale-notifier' => ['https://github.com/SRWieZ/tailscale-notifier.git'],
    // 'kitchen-sink' => ['https://github.com/NativePHP/kitchen-sink.git'],
    // 'relay' => ['https://github.com/gwleuverink/relay.git'],
]);

test('can create a bundle of a NativePHP project', function (string $repository) {
    $filesystem = new Filesystem;

    // Extract repository name from URL
    $repoName = basename($repository, '.git');
    $projectDir = $this->temp.'/'.$repoName;

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

})->with('repositories');
