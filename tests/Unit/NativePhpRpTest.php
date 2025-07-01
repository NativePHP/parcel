<?php

beforeEach(function () {
    require_once __DIR__.'/../../stubs/box-helper.php';
});

it('processes paths correctly outside PHAR context', function (mixed $input, mixed $expected) {
    expect(nativephp_rp($input))->toBe($expected);
})->with([
    'null input' => [null, null],
    'empty string' => ['', ''],
    'Unix absolute path' => ['/usr/local/bin', str_replace('/', DIRECTORY_SEPARATOR, '/usr/local/bin/')],
    'Windows absolute path' => ['C:/Program Files/test', str_replace('/', DIRECTORY_SEPARATOR, 'C:/Program Files/test')],
    'relative path without dot' => ['config/app.php', str_replace('/', DIRECTORY_SEPARATOR, getcwd().'/config/app.php')],
    'relative path with dot' => ['./config/app.php', str_replace('/', DIRECTORY_SEPARATOR, getcwd().'/config/app.php')],
    'parent directory path' => ['../vendor/autoload.php', str_replace('/', DIRECTORY_SEPARATOR, getcwd().'/../vendor/autoload.php')],
    'path with backslashes' => ['path\\to\\file.txt', str_replace('/', DIRECTORY_SEPARATOR, getcwd().'/path/to/file.txt')],
    'mixed separators' => ['path\\to/file.txt', str_replace('/', DIRECTORY_SEPARATOR, getcwd().'/path/to/file.txt')],
]);

it('processes paths correctly inside PHAR context', function (mixed $input, mixed $expected) {
    expect(nativephp_rp($input, 'phar:///app/test.phar'))->toBe($expected);
})->with([
    'relative path without dot' => ['config/app.php', str_replace('/', DIRECTORY_SEPARATOR, 'phar:///app/test.phar/config/app.php')],
    'relative path with dot' => ['./config/app.php', str_replace('/', DIRECTORY_SEPARATOR, 'phar:///app/test.phar/config/app.php')],
    'parent directory path' => ['../vendor/autoload.php', str_replace('/', DIRECTORY_SEPARATOR, 'phar:///app/test.phar/../vendor/autoload.php')],
    'path with backslashes' => ['path\\to\\file.txt', str_replace('/', DIRECTORY_SEPARATOR, 'phar:///app/test.phar/path/to/file.txt')],
    'mixed separators' => ['path\\to/file.txt', str_replace('/', DIRECTORY_SEPARATOR, 'phar:///app/test.phar/path/to/file.txt')],
]);
