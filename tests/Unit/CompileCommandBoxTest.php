<?php

use Parcel\Command\CompileCommand;
use Symfony\Component\Filesystem\Filesystem;

it('runs the Box installed alongside Parcel, not one from the project', function () {
    // Box has to load Parcel's compactors, so only the Box from Parcel's
    // own vendor/ will do, even when the project has a Box of its own.
    $project = sys_get_temp_dir().'/parcel-box-'.uniqid();
    (new Filesystem)->dumpFile($project.'/vendor/bin/box', "#!/usr/bin/env php\n<?php\n");

    try {
        $resolve = new ReflectionMethod(CompileCommand::class, 'resolveBoxBinary');
        $box = $resolve->invoke(new CompileCommand, $project);

        expect(realpath($box))->toBe(realpath(__DIR__.'/../../vendor/bin/box'));
    } finally {
        (new Filesystem)->remove($project);
    }
});
