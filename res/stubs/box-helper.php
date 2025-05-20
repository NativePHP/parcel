<?php

function nativephp_rp($value = null): mixed
{
    // Normalize slashes for cross-platform compatibility
    $pharPath = str_replace('\\', '/', Phar::running());
    $valPath = str_replace('\\', '/', $value);

    // Check for absolute paths (Windows and Unix)
    $isAbsolute = preg_match('/^(?:[a-zA-Z]:[\/\\\\]|\/)/', $valPath);

    if (str_contains($valPath, $pharPath) || $isAbsolute) {
        if (str_ends_with($valPath, '/') || is_dir($value->toString())) {
            $valPath = rtrim($valPath, '/\\') . '/';
        }
        return $valPath;
    }

    if (str_starts_with($valPath, './')) {
        $valPath = ltrim($valPath, '.');
    }

    // Ensure leading slash for relative paths
    $valPath = '/' . ltrim($valPath, '/\\');
    $fullPath = $pharPath . $valPath;

    if (is_dir($fullPath)) {
        $fullPath = rtrim($fullPath, '/\\') . '/';
    }

    return $fullPath;
}