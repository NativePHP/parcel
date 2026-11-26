<?php

use Parcel\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->dir = initTempDirectory();
    mkdir($this->dir.'/bootstrap', 0777, true);
    file_put_contents($this->dir.'/bootstrap/app.php', '<?php');
});

afterEach(function () {
    cleanupTempDirectory($this->dir);
});

function writeComposerJson(string $dir, string $json): void
{
    file_put_contents($dir.'/composer.json', $json);
}

function prepareApp(string $dir): CommandTester
{
    $tester = new CommandTester((new Application)->find('prepare'));
    $tester->execute(['target' => $dir, '--mobile' => true]);

    return $tester;
}

it('keeps empty objects as objects and empty lists as lists', function () {
    writeComposerJson($this->dir, <<<'JSON'
        {
            "name": "acme/app",
            "autoload": {
                "psr-4": {},
                "classmap": []
            },
            "autoload-dev": {},
            "config": {
                "allow-plugins": {}
            },
            "extra": {
                "laravel": {
                    "dont-discover": []
                }
            }
        }
        JSON);

    expect(prepareApp($this->dir)->getStatusCode())->toBe(0);

    $json = file_get_contents($this->dir.'/composer.json');
    $composer = json_decode($json);

    expect($composer->{'autoload-dev'})->toBeInstanceOf(stdClass::class)
        ->and($composer->config->{'allow-plugins'})->toBeInstanceOf(stdClass::class)
        ->and($composer->autoload->{'psr-4'})->toBeInstanceOf(stdClass::class)
        ->and($composer->autoload->classmap)->toBe([])
        ->and($composer->extra->laravel->{'dont-discover'})->toBe([])
        ->and($composer->autoload->files)->toBe(['box-helper.php'])
        ->and($composer->scripts->{'post-autoload-dump'})->toBe([]);

    $composerBinary = (new ExecutableFinder)->find('composer');
    if ($composerBinary === null) {
        $this->markTestIncomplete('composer is not on the PATH, so dump-autoload was not checked');
    }

    $process = new Process([$composerBinary, 'dump-autoload', '--no-interaction'], $this->dir);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($this->dir.'/vendor/autoload.php')->toBeFile();
});

it('adds box-helper.php after existing autoload files and drops testbench scripts', function () {
    writeComposerJson($this->dir, <<<'JSON'
        {
            "autoload": {
                "files": ["app/helpers.php"]
            },
            "scripts": {
                "post-autoload-dump": [
                    "@php vendor/bin/testbench package:discover",
                    "@php artisan package:discover --ansi"
                ]
            }
        }
        JSON);

    expect(prepareApp($this->dir)->getStatusCode())->toBe(0);

    $composer = json_decode(file_get_contents($this->dir.'/composer.json'), true);

    expect($composer['autoload']['files'])->toBe(['app/helpers.php', 'box-helper.php'])
        ->and($composer['scripts']['post-autoload-dump'])->toBe(['@php artisan package:discover --ansi']);
});

it('rejects a composer.json that is not an object', function () {
    writeComposerJson($this->dir, '[]');

    expect(prepareApp($this->dir)->getStatusCode())->toBe(1);
});
