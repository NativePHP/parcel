<?php

declare(strict_types=1);

namespace Parcel\Compactor;

use KevinGH\Box\Compactor\Compactor;

class RealPathMod implements Compactor
{
    private const FILE_PATTERNS = [
        'SplFileInfo' => '/[\/\\\\]SplFileInfo\.php$/',
        'LoadConfiguration' => '/[\/\\\\]LoadConfiguration\.php$/',
        'BladeIconsServiceProvider' => '/[\/\\\\]BladeIconsServiceProvider\.php$/',
    ];

    public function compact(string $file, string $contents): string
    {
        $normalizedFile = str_replace('\\', '/', $file);

        // Handle SplFileInfo modifications
        if (preg_match(self::FILE_PATTERNS['SplFileInfo'], $normalizedFile)) {
            return $this->modifySplFileInfo($contents);
        }

        // Handle LoadConfiguration modifications  
        if (preg_match(self::FILE_PATTERNS['LoadConfiguration'], $normalizedFile)) {
            return $this->modifyLoadConfiguration($contents);
        }

        // Handle Blade Icons service provider
        if (preg_match(self::FILE_PATTERNS['BladeIconsServiceProvider'], $normalizedFile)) {
            return $this->modifyBladeIconsServiceProvider($contents);
        }

        // Handle general PHP files for realpath replacement
        if (str_ends_with($normalizedFile, '.php')) {
            return $this->replaceRealpathCalls($contents);
        }

        return $contents;
    }

    private function modifySplFileInfo(string $contents): string
    {
        if (!str_contains($contents, 'getRealPath')) {
            $trimmed = rtrim(trim($contents), '}');
            return $trimmed . "\n    public function getRealPath(): string|false\n    {\n        return \$this->getPathname() ?? false;\n    }\n}";
        }
        
        return $contents;
    }

    private function modifyLoadConfiguration(string $contents): string
    {
        return str_replace(
            '$directory = $file->getPath();',
            '$directory = \\nativephp_rp($file->getPath());',
            $contents
        );
    }

    private function modifyBladeIconsServiceProvider(string $contents): string
    {
        $pattern = '/return \$this->app->bootstrapPath\(\'cache\/blade-icons\.php\'\);/';
        $replacement = 'if (!empty(env(\'APP_SERVICES_CACHE\'))) {' .
                      'return dirname(env(\'APP_SERVICES_CACHE\')) . DIRECTORY_SEPARATOR . \'blade-icons.php\';' .
                      '} return $this->app->bootstrapPath(\'cache/blade-icons.php\');';

        return preg_replace($pattern, $replacement, $contents);
    }

    private function replaceRealpathCalls(string $contents): string
    {
        // Handle fully qualified calls (\realpath) - be more precise with negative lookbehind
        $contents = preg_replace('/(?<![a-zA-Z0-9_])\\\\realpath\(/', '\\nativephp_rp(', $contents);

        // Handle regular calls (realpath) - be more precise with negative lookbehind  
        $contents = preg_replace('/(?<![a-zA-Z0-9_\\\\])realpath\(/', '\\nativephp_rp(', $contents);

        return $contents;
    }
}