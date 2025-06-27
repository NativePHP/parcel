<?php

function nativephp_rp($value = null): mixed
{
    // Normalize slashes for cross-platform compatibility
    $pharPath = str_replace('\\', '/', Phar::running());
    $valPath = str_replace('\\', '/', $value);

    // Check for absolute paths (Windows and Unix)
    $isAbsolute = preg_match('/^(?:[a-zA-Z]:[\/\\\\]|\/)/', $valPath);

    // If it's an absolute path, just return it with a trailing slash
    if (str_contains($valPath, $pharPath) || $isAbsolute) {
        // If the path is a directory, ensure it has a trailing slash
        if (str_ends_with($valPath, '/') || is_dir($value)) {
            $valPath = rtrim($valPath, '/\\') . '/';
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $valPath);
    }

    // Check for relative paths
    if (str_starts_with($valPath, './')) {
        $valPath = ltrim($valPath, '.');
    }

    // Ensure leading slash for relative paths
    $valPath = '/' . ltrim($valPath, '/\\');
    $fullPath = $pharPath . $valPath;

    // If the path is a directory, ensure it has a trailing slash
    if (str_ends_with($fullPath, '/') || is_dir($fullPath)) {
        $fullPath = rtrim($fullPath, '/\\') . '/';
    }

    return str_replace('/', DIRECTORY_SEPARATOR, $fullPath);
}