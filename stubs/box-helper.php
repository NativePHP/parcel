<?php

function nativephp_rp($value = null, $pharPath = null, string $separator = DIRECTORY_SEPARATOR): mixed
{
    if ($value === null || $value === '') {
        return $value;
    }

    // Normalize slashes for cross-platform compatibility
    if ($pharPath === null) {
        $pharPath = str_replace('\\', '/', Phar::running());
    } else {
        $pharPath = str_replace('\\', '/', $pharPath);
    }
    $valPath = str_replace('\\', '/', (string) $value);

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

        return nativephp_rp_separators($normalizedPath, $separator);
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

    return nativephp_rp_separators($fullPath, $separator);
}

/**
 * Real paths get the platform's separator. phar:// paths are stream URLs
 * and keep their forward slashes: turned into "phar:\\C:\\..." on Windows,
 * PHP no longer reads them as a phar, and a bundled app can't even find
 * its config directory.
 */
function nativephp_rp_separators(string $path, string $separator): string
{
    return str_starts_with($path, 'phar://') ? $path : str_replace('/', $separator, $path);
}

/**
 * glob() that can see inside a phar.
 *
 * PHP's glob() only reads the real filesystem, so under a phar:// path it
 * quietly returns nothing. Laravel finds migrations and discovers event
 * listeners with it, so in a bundled app those just go missing. Outside a
 * phar this is plain glob().
 */
function nativephp_glob(string $pattern, int $flags = 0): array|false
{
    if (! str_starts_with($pattern, 'phar://')) {
        return glob($pattern, $flags);
    }

    $brace = defined('GLOB_BRACE') ? GLOB_BRACE : 0;
    $noSort = defined('GLOB_NOSORT') ? GLOB_NOSORT : 0;
    $noCheck = defined('GLOB_NOCHECK') ? GLOB_NOCHECK : 0;

    $patterns = ($flags & $brace) ? nativephp_glob_expand_braces($pattern) : [$pattern];

    // Like glob(), each brace alternative is sorted on its own and the
    // alternatives keep their order.
    $matches = [];

    foreach ($patterns as $expanded) {
        $found = nativephp_glob_phar($expanded, $flags);

        if (! ($flags & $noSort)) {
            sort($found, SORT_STRING);
        }

        array_push($matches, ...$found);
    }

    $matches = array_values(array_unique($matches));

    if ($matches === [] && ($flags & $noCheck)) {
        return [$pattern];
    }

    return $matches;
}

/**
 * @internal
 */
function nativephp_glob_phar(string $pattern, int $flags): array
{
    $escape = ! ($flags & (defined('GLOB_NOESCAPE') ? GLOB_NOESCAPE : 0));
    $onlyDir = (bool) ($flags & (defined('GLOB_ONLYDIR') ? GLOB_ONLYDIR : 0));
    $mark = (bool) ($flags & (defined('GLOB_MARK') ? GLOB_MARK : 0));

    // A trailing slash asks for directories only, as it does for glob().
    if (str_ends_with($pattern, '/') && strlen($pattern) > strlen('phar:///')) {
        $pattern = rtrim($pattern, '/');
        $onlyDir = true;
        $mark = true;
    }

    $segments = explode('/', substr($pattern, strlen('phar://')));

    // Everything up to the first segment with a wildcard is a literal path.
    $base = [];

    while ($segments !== [] && ! nativephp_glob_has_magic($segments[0], $escape)) {
        $segment = array_shift($segments);
        $base[] = $escape ? preg_replace('/\\\\(.)/s', '$1', $segment) : $segment;
    }

    $candidates = ['phar://'.implode('/', $base)];

    $last = count($segments) - 1;

    foreach ($segments as $index => $segment) {
        $next = [];

        foreach ($candidates as $directory) {
            $entries = is_dir($directory) ? scandir($directory) : false;

            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (! nativephp_glob_match($segment, $entry, $escape)) {
                    continue;
                }

                $path = $directory.'/'.$entry;

                if ($index < $last && ! is_dir($path)) {
                    continue;
                }

                $next[] = $path;
            }
        }

        $candidates = $next;
    }

    $matches = [];

    foreach ($candidates as $path) {
        if (! file_exists($path)) {
            continue;
        }

        $isDir = is_dir($path);

        if ($onlyDir && ! $isDir) {
            continue;
        }

        $matches[] = $mark && $isDir ? $path.'/' : $path;
    }

    return $matches;
}

/**
 * @internal
 */
function nativephp_glob_has_magic(string $segment, bool $escape): bool
{
    $length = strlen($segment);

    for ($i = 0; $i < $length; $i++) {
        if ($escape && $segment[$i] === '\\') {
            $i++;

            continue;
        }

        if ($segment[$i] === '*' || $segment[$i] === '?' || $segment[$i] === '[') {
            return true;
        }
    }

    return false;
}

/**
 * Match one path segment against one glob segment, the way glob() does:
 * a leading dot has to be matched by a leading dot in the pattern.
 *
 * Not fnmatch(): PHP built for iOS doesn't have it.
 *
 * @internal
 */
function nativephp_glob_match(string $segment, string $name, bool $escape): bool
{
    if (str_starts_with($name, '.') && ! str_starts_with($segment, '.') && ! str_starts_with($segment, '\\.')) {
        return false;
    }

    $regex = '';
    $length = strlen($segment);

    for ($i = 0; $i < $length; $i++) {
        $char = $segment[$i];

        if ($escape && $char === '\\' && $i + 1 < $length) {
            $regex .= preg_quote($segment[++$i], '#');

            continue;
        }

        if ($char === '*') {
            $regex .= '.*';

            continue;
        }

        if ($char === '?') {
            $regex .= '.';

            continue;
        }

        if ($char === '[') {
            // A ']' straight after the '[' (or '[!') is part of the class.
            $close = $i + 1;

            if ($close < $length && ($segment[$close] === '!' || $segment[$close] === '^')) {
                $close++;
            }

            if ($close < $length && $segment[$close] === ']') {
                $close++;
            }

            $close = strpos($segment, ']', $close);

            if ($close === false) {
                $regex .= '\\[';

                continue;
            }

            $class = substr($segment, $i + 1, $close - $i - 1);
            $negate = $class !== '' && ($class[0] === '!' || $class[0] === '^');
            $class = $negate ? substr($class, 1) : $class;

            $body = '';

            for ($j = 0, $classLength = strlen($class); $j < $classLength; $j++) {
                if ($escape && $class[$j] === '\\' && $j + 1 < $classLength) {
                    $body .= '\\'.$class[++$j];
                } elseif (in_array($class[$j], ['\\', '[', ']', '^', '#'], true)) {
                    $body .= '\\'.$class[$j];
                } else {
                    $body .= $class[$j];
                }
            }

            $regex .= '['.($negate ? '^' : '').$body.']';
            $i = $close;

            continue;
        }

        $regex .= preg_quote($char, '#');
    }

    return (bool) preg_match('#^'.$regex.'$#s', $name);
}

/**
 * Expand the first {a,b} group (and, recursively, any that follow).
 *
 * @internal
 */
function nativephp_glob_expand_braces(string $pattern): array
{
    $length = strlen($pattern);
    $depth = 0;
    $start = null;

    for ($i = 0; $i < $length; $i++) {
        $char = $pattern[$i];

        if ($char === '\\') {
            $i++;

            continue;
        }

        if ($char === '{') {
            if ($depth === 0) {
                $start = $i;
            }

            $depth++;

            continue;
        }

        if ($char !== '}' || $depth === 0) {
            continue;
        }

        $depth--;

        if ($depth > 0) {
            continue;
        }

        $inner = substr($pattern, $start + 1, $i - $start - 1);
        $prefix = substr($pattern, 0, $start);
        $suffix = substr($pattern, $i + 1);

        $options = [];
        $current = '';
        $level = 0;

        for ($j = 0, $innerLength = strlen($inner); $j < $innerLength; $j++) {
            $c = $inner[$j];

            if ($c === '\\' && $j + 1 < $innerLength) {
                $current .= $c.$inner[++$j];

                continue;
            }

            if ($c === '{') {
                $level++;
            } elseif ($c === '}') {
                $level--;
            } elseif ($c === ',' && $level === 0) {
                $options[] = $current;
                $current = '';

                continue;
            }

            $current .= $c;
        }

        $options[] = $current;

        $expanded = [];

        foreach ($options as $option) {
            array_push($expanded, ...nativephp_glob_expand_braces($prefix.$option.$suffix));
        }

        return $expanded;
    }

    return [$pattern];
}
