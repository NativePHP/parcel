<?php

function nativephp_rp($value = null): mixed
{
    if ($value === null || $value === '') {
        return $value;
    }

    // Normalize slashes for cross-platform compatibility
    $pharPath = str_replace('\\', '/', Phar::running());
    $valPath = str_replace('\\', '/', (string) $value);

    // Check for absolute paths (Windows: C:/, D:/, etc. and Unix: /)
    $isAbsolute = preg_match('/^(?:[a-zA-Z]:[\/\\\\]|\/|\\\\)/', $valPath);

    // If it's already an absolute path or contains PHAR path, normalize and return
    if (str_contains($valPath, $pharPath) || $isAbsolute) {
        // Clean up the path
        $normalizedPath = $valPath;
        
        // For directories, ensure trailing slash (but only if it's actually a directory)
        if (is_dir($value) && !str_ends_with($normalizedPath, '/')) {
            $normalizedPath .= '/';
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    }

    // Handle relative paths
    if (str_starts_with($valPath, './')) {
        $valPath = substr($valPath, 2);
    } elseif (str_starts_with($valPath, '../')) {
        // Handle parent directory references
        $valPath = '/' . $valPath;
    }

    // Ensure leading slash for relative paths and combine with PHAR path
    $valPath = '/' . ltrim($valPath, '/\\');
    $fullPath = $pharPath . $valPath;

    // For directories, ensure trailing slash (but only if it's actually a directory)
    if (is_dir($fullPath) && !str_ends_with($fullPath, '/')) {
        $fullPath .= '/';
    }

    return str_replace('/', DIRECTORY_SEPARATOR, $fullPath);
}