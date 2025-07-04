<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class)->in('Feature');
uses(\PHPUnit\Framework\TestCase::class)->in('Unit');

function initTempDirectory(): void
{
    $filesystem = new Filesystem;
    // $tempDir = sys_get_temp_dir() . '/parcel-test-' . uniqid();
    $tempDir = __DIR__.'/temp'.DIRECTORY_SEPARATOR.'parcel-test-'.uniqid();
    $filesystem->mkdir($tempDir);
    test()->temp = $tempDir;
}

function clearTempDirectory(): void
{
    $filesystem = new Filesystem;
    if (isset(test()->temp) && $filesystem->exists(test()->temp)) {
        $filesystem->remove(test()->temp);
    }
}

function dumpTempDirectory()
{
    dump(test()->temp);
}

function runProcessInTemp(array $args, $cwd = null): Process
{
    $cwd = $cwd ?: test()->temp;
    $process = new Process($args, $cwd);
    $process->setTimeout(300); // 5 minutes timeout
    $process->run();

    return $process;
}
