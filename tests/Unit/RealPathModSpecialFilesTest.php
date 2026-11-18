<?php

use Parcel\Compactor\RealPathMod;

it('still rewrites realpath() in LoadConfiguration, so the app config is found', function () {
    $code = <<<'PHP'
    <?php
    class LoadConfiguration {
        protected function getConfigurationFiles($app) {
            $configPath = realpath($app->configPath());
            return $configPath;
        }
        protected function getNestedDirectory($file, $configPath) {
            $directory = $file->getPath();
            return $directory;
        }
    }
    PHP;

    $compacted = (new RealPathMod)->compact('/app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/LoadConfiguration.php', $code);

    expect($compacted)
        ->toContain('$configPath = \nativephp_rp($app->configPath());')
        ->toContain('$directory = \nativephp_rp($file->getPath());')
        ->not->toContain(' realpath(');
});

it('adds getRealPath() to SplFileInfo without breaking it', function () {
    $code = "<?php\nnamespace Symfony\\Component\\Finder;\nclass SplFileInfo extends \\SplFileInfo\n{\n    public function x() { return realpath(\$this->getPathname()); }\n}\n";

    $compacted = (new RealPathMod)->compact('/app/vendor/symfony/finder/SplFileInfo.php', $code);

    expect($compacted)
        ->toContain('public function getRealPath(): string|false')
        ->toContain('return \nativephp_rp($this->getPathname());');

    exec('echo '.escapeshellarg($compacted).' | '.escapeshellarg(PHP_BINARY).' -l', $output, $status);
    expect($status)->toBe(0);
});

it('leaves files that are not PHP alone', function () {
    expect((new RealPathMod)->compact('/app/composer.json', '{"x": "realpath(y)"}'))->toBe('{"x": "realpath(y)"}');
});
