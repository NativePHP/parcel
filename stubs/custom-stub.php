#!/usr/bin/env php
<?php

/**
 * This is a NativePHP bundle
 * https://nativephp.com/
 *
 * Custom PHAR stub for NativePHP applications
 * Allows cli-server SAPI for PHP built-in server (php -S)
 */

// Allow CLI and CLI-server SAPIs (cli-server is needed for php -S)
if (! in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg', 'embed'], true)) {
    echo PHP_EOL.'The application may only be invoked from a command line or built-in server, got "'.PHP_SAPI.'"'.PHP_EOL;
    exit(1);
}

Phar::mapPhar('__nativephp_app_bundle');

// Configure runtime paths based on NATIVEPHP_APP_DATA_PATH
$appDataPath = getenv('NATIVEPHP_APP_DATA_PATH');
if ($appDataPath !== false && $appDataPath !== '') {
    $appDir = rtrim($appDataPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'app';

    // Create external directory structure
    $storagePath = $appDir.DIRECTORY_SEPARATOR.'storage';
    $storageLogs = $storagePath.DIRECTORY_SEPARATOR.'logs';
    $storageFramework = $storagePath.DIRECTORY_SEPARATOR.'framework';
    $bootstrapCache = $appDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
    $database = $appDir.DIRECTORY_SEPARATOR.'database';

    $directories = [
        $storageLogs,
        $storageFramework.DIRECTORY_SEPARATOR.'cache',
        $storageFramework.DIRECTORY_SEPARATOR.'sessions',
        $storageFramework.DIRECTORY_SEPARATOR.'views',
        $storageFramework.DIRECTORY_SEPARATOR.'testing',
        $bootstrapCache,
        $database,
    ];

    foreach ($directories as $dir) {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Create empty database.sqlite if it doesn't exist
    $databaseFile = $database.DIRECTORY_SEPARATOR.'database.sqlite';
    if (! file_exists($databaseFile)) {
        touch($databaseFile);
    }

    // Mount external directories to PHAR paths using relative paths
    Phar::mount('storage/logs', $storageLogs);
    Phar::mount('storage/framework', $storageFramework);
    Phar::mount('bootstrap/cache', $bootstrapCache);
    Phar::mount('database', $database);
}

require 'phar://__nativephp_app_bundle/phar-router.php';

__HALT_COMPILER();
