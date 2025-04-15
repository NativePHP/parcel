<?php

namespace KevinGH\Box\Compactor;

class RealPathMod implements Compactor
{
    public function compact(string $file, string $contents): string
    {
        if (preg_match('/.*\/SplFileInfo\.php/', $file)) {
            return rtrim(trim($contents), '}') . "\npublic function getRealPath():string|false {return \$this->getPathname() ?? false;}\n}";
        }

        if (preg_match('/.*\/LoadConfiguration\.php/', $file)) {
            return str_replace('$directory = $file->getPath();', '$directory = nativephp_rp($file->getPath());', $contents);
        }

        if (preg_match('/.*\/BladeIconsServiceProvider\.php/', $file)) {
            // Hotfix for Blade Icons
            $pattern = '/return \$this->app->bootstrapPath\(\'cache\/blade-icons\.php\'\);/';
            $replacement = 'if ( ! empty(env(\'APP_SERVICES_CACHE\'))) {return dirname(env(\'APP_SERVICES_CACHE\')).DIRECTORY_SEPARATOR.\'blade-icons.php\';} return $this->app->bootstrapPath(\'cache/blade-icons.php\');';

            return preg_replace($pattern, $replacement, $contents);
        }

        if (preg_match('/\.php/', $file)) {
            // Handle fully qualified calls (\realpath)
            $contents = preg_replace('/([^>a-zA-Z0-9_\\\\])\\\\realpath\(/', '$1\\nativephp_rp(', $contents);

            // Handle regular calls (realpath)
            $contents = preg_replace('/([^>a-zA-Z0-9_\\\\])realpath\(/', '$1\\nativephp_rp(', $contents);

            return $contents;
        }

        return $contents;
    }
}
