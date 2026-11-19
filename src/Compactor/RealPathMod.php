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

        if (! str_ends_with($normalizedFile, '.php')) {
            return $contents;
        }

        // A few files need more than the general rewrites below, never
        // instead of them: LoadConfiguration also calls realpath() on the
        // config path, and left alone it finds no config files at all.
        if (preg_match(self::FILE_PATTERNS['SplFileInfo'], $normalizedFile)) {
            $contents = $this->modifySplFileInfo($contents);
        } elseif (preg_match(self::FILE_PATTERNS['LoadConfiguration'], $normalizedFile)) {
            $contents = $this->modifyLoadConfiguration($contents);
        } elseif (preg_match(self::FILE_PATTERNS['BladeIconsServiceProvider'], $normalizedFile)) {
            $contents = $this->modifyBladeIconsServiceProvider($contents);
        }

        return $this->replaceGlobCalls($this->replaceRealpathCalls($contents));
    }

    /**
     * Point calls to the global glob() at nativephp_glob(), which can see
     * inside the phar. Works on tokens so that methods called glob, like
     * Filesystem::glob(), keep their declarations and call sites.
     */
    private function replaceGlobCalls(string $contents): string
    {
        if (stripos($contents, 'glob') === false) {
            return $contents;
        }

        $tokens = \PhpToken::tokenize($contents);
        $count = count($tokens);
        $previous = null;
        $output = '';

        foreach ($tokens as $index => $token) {
            if ($this->isGlobFunctionCall($token, $previous, $tokens, $index, $count)) {
                $output .= '\\nativephp_glob';
                $previous = $token;

                continue;
            }

            $output .= $token->text;

            if (! $token->isIgnorable()) {
                $previous = $token;
            }
        }

        return $output;
    }

    /**
     * @param  list<\PhpToken>  $tokens
     */
    private function isGlobFunctionCall(\PhpToken $token, ?\PhpToken $previous, array $tokens, int $index, int $count): bool
    {
        $name = strtolower($token->text);

        if (! ($token->is(T_STRING) && $name === 'glob') && ! ($token->is(T_NAME_FULLY_QUALIFIED) && $name === '\\glob')) {
            return false;
        }

        // A declaration, a method or static call, or a constant: not ours.
        if ($previous?->is([T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_CONST])) {
            return false;
        }

        for ($next = $index + 1; $next < $count; $next++) {
            if (! $tokens[$next]->isIgnorable()) {
                return $tokens[$next]->text === '(';
            }
        }

        return false;
    }

    private function modifySplFileInfo(string $contents): string
    {
        if (! str_contains($contents, 'getRealPath')) {
            $trimmed = rtrim(trim($contents), '}');

            return $trimmed."\n    public function getRealPath(): string|false\n    {\n        return \$this->getPathname() ?? false;\n    }\n}";
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
        $replacement = 'if (!empty(env(\'APP_SERVICES_CACHE\'))) {'.
                      'return dirname(env(\'APP_SERVICES_CACHE\')) . DIRECTORY_SEPARATOR . \'blade-icons.php\';'.
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
