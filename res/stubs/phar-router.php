<?php

// Determine if we're running in a CLI or under a SAPI and do the right thing

if (php_sapi_name() == "cli" && $argc > 0 && $argv[1] === 'artisan') {
    array_shift($argv);
    $_SERVER['argv'] = $argv;
    require 'artisan';
    return;
}

$url = $_SERVER['REQUEST_URI'];

error_log($url);

if ($url !== '/' && file_exists('public'.$url)) {
    $ext = pathinfo($url, PATHINFO_EXTENSION);

    $mimes = [
        'css' => 'text/css',
        'js' => 'application/x-javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];

    $mime = $mimes[$ext] ?? 'text/html';

    header("Content-Type: {$mime}");

    require 'public'.$url;
    return;
}

require 'public/index.php';
