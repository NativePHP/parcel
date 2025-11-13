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

require 'phar://__nativephp_app_bundle/phar-router.php';

__HALT_COMPILER();
