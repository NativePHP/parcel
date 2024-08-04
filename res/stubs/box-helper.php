<?php

function nativephp_rp($value = null): mixed
{
    // Force the path to be relative to the PHAR root
    $path = str(Phar::running());
    $value = str($value);

    if ($value->contains($path) || $value->startsWith('/')) {
        if ($value->endsWith('/') || is_dir($value->toString())) {
            $value = $value->finish('/');
        }

        return $value->toString();
    }

    if ($value->startsWith('./')) {
        $value = $value->ltrim('.');
    }

    $path = $path->append($value->start('/'));

    if (is_dir($path)) {
        $path = $path->finish('/');
    }

    return $path->toString();
}
