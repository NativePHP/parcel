<?php

beforeEach(function () {
    require_once __DIR__.'/../../stubs/box-helper.php';

    $this->dir = sys_get_temp_dir().'/parcel-glob-'.uniqid();
    mkdir($this->dir.'/src/database/migrations', 0777, true);
    mkdir($this->dir.'/src/app/Listeners/Nested', 0777, true);
    mkdir($this->dir.'/src/fonts', 0777, true);

    foreach ([
        'database/migrations/2024_01_02_000000_b.php',
        'database/migrations/2024_01_01_000000_a.php',
        'database/migrations/README',
        'database/migrations/.hidden_x.php',
        'app/Listeners/SendEmail.php',
        'app/Listeners/Nested/Other.php',
        'fonts/b.otf',
        'fonts/a.ttf',
        'fonts/c.woff',
        'weird[1].txt',
    ] as $file) {
        file_put_contents($this->dir.'/src/'.$file, '<?php');
    }

    // Writing a phar needs phar.readonly off, which only the command line
    // (or php.ini) can do.
    $this->pharFile = $this->dir.'/app.phar';
    $build = sprintf(
        '$p = new Phar(%s); $p->buildFromDirectory(%s); $p->setStub("<?php __HALT_COMPILER();");',
        var_export($this->pharFile, true),
        var_export($this->dir.'/src', true),
    );
    exec(escapeshellarg(PHP_BINARY).' -d phar.readonly=0 -r '.escapeshellarg($build), $output, $status);
    expect($status)->toBe(0);

    $this->root = 'phar://'.$this->pharFile;
});

afterEach(function () {
    if (isset($this->dir)) {
        exec('rm -rf '.escapeshellarg($this->dir));
    }
});

it('finds files inside a phar that glob() cannot see', function () {
    expect(glob($this->root.'/database/migrations/*_*.php'))->toBe([]);

    expect(nativephp_glob($this->root.'/database/migrations/*_*.php'))->toBe([
        $this->root.'/database/migrations/2024_01_01_000000_a.php',
        $this->root.'/database/migrations/2024_01_02_000000_b.php',
    ]);
});

it('matches wildcards in directory segments', function () {
    expect(nativephp_glob($this->root.'/*/Listeners/*.php'))->toBe([
        $this->root.'/app/Listeners/SendEmail.php',
    ]);
});

it('honours GLOB_ONLYDIR and GLOB_MARK', function () {
    expect(nativephp_glob($this->root.'/app/Listeners/*', GLOB_ONLYDIR))->toBe([
        $this->root.'/app/Listeners/Nested',
    ]);

    expect(nativephp_glob($this->root.'/app/*', GLOB_MARK))->toBe([
        $this->root.'/app/Listeners/',
    ]);

    expect(nativephp_glob($this->root.'/app/Listeners/*/'))->toBe([
        $this->root.'/app/Listeners/Nested/',
    ]);
});

it('expands braces in order, like glob()', function () {
    expect(nativephp_glob($this->root.'/fonts/*.{ttf,otf}', GLOB_BRACE))->toBe([
        $this->root.'/fonts/a.ttf',
        $this->root.'/fonts/b.otf',
    ]);
});

it('returns a literal path only when it exists', function () {
    expect(nativephp_glob($this->root.'/fonts/a.ttf'))->toBe([$this->root.'/fonts/a.ttf'])
        ->and(nativephp_glob($this->root.'/fonts/missing.ttf'))->toBe([]);
});

it('returns the pattern for GLOB_NOCHECK when nothing matches', function () {
    expect(nativephp_glob($this->root.'/nope/*', GLOB_NOCHECK))->toBe([$this->root.'/nope/*']);
});

it('treats escaped brackets as literals', function () {
    expect(nativephp_glob($this->root.'/weird\[1\].txt'))->toBe([$this->root.'/weird[1].txt']);
});

it('leaves paths outside a phar to glob()', function () {
    expect(nativephp_glob($this->dir.'/src/fonts/*.ttf'))->toBe(glob($this->dir.'/src/fonts/*.ttf'));
});

it('matches names the way fnmatch() does', function (string $pattern, string $name) {
    expect(nativephp_glob_match($pattern, $name, true))
        ->toBe(fnmatch($pattern, $name, FNM_PERIOD));
})->with([
    ['*_*.php', '2024_01_01_a.php'],
    ['*_*.php', 'README'],
    ['*.php', '.hidden.php'],
    ['.*', '.hidden.php'],
    ['?.txt', 'a.txt'],
    ['?.txt', 'ab.txt'],
    ['[abc].ttf', 'b.ttf'],
    ['[!abc].ttf', 'b.ttf'],
    ['[!abc].ttf', 'd.ttf'],
    ['[a-c]*', 'cat'],
    ['[a-c]*', 'dog'],
    ['[]x].txt', '].txt'],
    ['weird\[1\].txt', 'weird[1].txt'],
    ['weird\[1\].txt', 'weird1.txt'],
    ['a#b.php', 'a#b.php'],
    ['file.(1)+', 'file.(1)+'],
]);

it('works without fnmatch(), which PHP built for iOS lacks', function () {
    $script = sprintf(
        'require %s; echo json_encode(nativephp_glob(%s));',
        var_export(realpath(__DIR__.'/../../stubs/box-helper.php'), true),
        var_export($this->root.'/database/migrations/*_*.php', true),
    );

    exec(escapeshellarg(PHP_BINARY).' -d disable_functions=fnmatch -r '.escapeshellarg($script).' 2>&1', $output, $status);

    expect($status)->toBe(0)
        ->and(json_decode(implode('', $output), true))->toBe([
            $this->root.'/database/migrations/2024_01_01_000000_a.php',
            $this->root.'/database/migrations/2024_01_02_000000_b.php',
        ]);
});
