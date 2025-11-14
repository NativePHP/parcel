<?php

function nativephp_rp($value = null, $pharPath = null): mixed
{
    if ($value === null || $value === '') {
        return $value;
    }

    // Normalize slashes for cross-platform compatibility
    $valPath = str_replace('\\', '/', (string) $value);

    // Check if NATIVEPHP_APP_DATA_PATH is set and path should use mounted external directory
    $appDataPath = getenv('NATIVEPHP_APP_DATA_PATH');
    if ($appDataPath !== false && $appDataPath !== '') {
        // Normalize the value path
        $normalizedValue = ltrim($valPath, '/');

        // Check if path starts with mounted directories (storage, database, or bootstrap/cache)
        if (preg_match('#^(storage(/|$)|database(/|$)|bootstrap/cache(/|$))#', $normalizedValue)) {
            // Return direct filesystem path to external mounted directory
            $externalPath = rtrim($appDataPath, '/\\').'/app/'.$normalizedValue;

            return str_replace('/', DIRECTORY_SEPARATOR, $externalPath);
        }
    }

    // Original realpath logic for non-mounted paths
    if ($pharPath === null) {
        $pharPath = str_replace('\\', '/', Phar::running());
    } else {
        $pharPath = str_replace('\\', '/', $pharPath);
    }

    // Check for absolute paths (Windows: C:/, D:/, etc. and Unix: /)
    $isAbsolute = preg_match('/^(?:[a-zA-Z]:[\/\\\\]|\/|\\\\)/', $valPath);

    // If it's already an absolute path or contains PHAR path, normalize and return
    if ((! empty($pharPath) && str_contains($valPath, $pharPath)) || $isAbsolute) {
        // Clean up the path
        $normalizedPath = $valPath;

        // For directories, ensure trailing slash (but only if it's actually a directory)
        if (is_dir($value) && ! str_ends_with($normalizedPath, '/')) {
            $normalizedPath .= '/';
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    }

    // Handle relative paths
    if (str_starts_with($valPath, './')) {
        $valPath = substr($valPath, 2);
    } elseif (str_starts_with($valPath, '../')) {
        // Handle parent directory references
        $valPath = '/'.$valPath;
    }

    // Ensure leading slash for relative paths and combine with PHAR path
    $valPath = '/'.ltrim($valPath, '/\\');

    // If not in PHAR context, make absolute using current working directory
    if (empty($pharPath)) {
        $fullPath = str_replace('\\', '/', getcwd()).$valPath;
    } else {
        $fullPath = $pharPath.$valPath;
    }

    // For directories, ensure trailing slash (but only if it's actually a directory)
    if (is_dir($fullPath) && ! str_ends_with($fullPath, '/')) {
        $fullPath .= '/';
    }

    return str_replace('/', DIRECTORY_SEPARATOR, $fullPath);
}
