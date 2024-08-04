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

        if (preg_match('/\.php/', $file)) {
            return preg_replace('/([^>a-z])realpath\(/', '$1nativephp_rp(', $contents);
        }

        return $contents;
    }
}
