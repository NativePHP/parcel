<?php

use Parcel\Compactor\RealPathMod;

function compactGlob(string $code): string
{
    return (new RealPathMod)->compact('/app/vendor/foo/src/Thing.php', $code);
}

it('points global glob() calls at nativephp_glob()', function (string $code, string $expected) {
    expect(compactGlob($code))->toBe($expected);
})->with([
    'plain call' => ['<?php $f = glob($p."/*.php");', '<?php $f = \nativephp_glob($p."/*.php");'],
    'fully qualified call' => ['<?php $f = \glob($p, GLOB_ONLYDIR);', '<?php $f = \nativephp_glob($p, GLOB_ONLYDIR);'],
    'space before parenthesis' => ['<?php glob ($p);', '<?php \nativephp_glob ($p);'],
    'namespaced file' => ["<?php namespace A;\nforeach (glob(\$d) as \$x) {}", "<?php namespace A;\nforeach (\\nativephp_glob(\$d) as \$x) {}"],
]);

it('leaves methods and other names called glob alone', function (string $code) {
    expect(compactGlob($code))->toBe($code);
})->with([
    'method declaration' => ['<?php class F { public function glob($pattern, $flags = 0) { return 1; } }'],
    'method call' => ['<?php $this->files->glob($p);'],
    'nullsafe method call' => ['<?php $files?->glob($p);'],
    'static call' => ['<?php File::glob($p);'],
    'qualified function' => ['<?php Foo\glob($p);'],
    'constant' => ['<?php echo GLOB_BRACE;'],
    'string' => ['<?php $s = "glob(x)";'],
    'use function import' => ['<?php use function glob;'],
]);
