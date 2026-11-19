<?php

use Parcel\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/parcel-mobile-'.uniqid();
    mkdir($this->dir.'/bootstrap', 0777, true);
    file_put_contents($this->dir.'/bootstrap/app.php', '<?php');
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->dir));
});

function pushStubs(array $input): CommandTester
{
    $tester = new CommandTester((new Application)->find('push-stubs'));
    $tester->execute($input);

    return $tester;
}

it('pushes the mobile configuration to an app staged without artisan', function () {
    $tester = pushStubs(['target' => $this->dir, '--mobile' => true]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($this->dir.'/box.json')->toBeFile()
        ->and($this->dir.'/box-helper.php')->toBeFile()
        ->and($this->dir.'/mobile-stub.php')->toBeFile()
        ->and($this->dir.'/phar-router.php')->not->toBeFile();

    $config = json_decode(file_get_contents($this->dir.'/box.json'), true);

    // The runtime opens files inside the bundle by phar:// path, which only
    // works when the archive's file name has an extension.
    expect($config['output'])->toEndWith('.phar')
        ->and($config['stub'])->toBe('mobile-stub.php')
        ->and($config['dump-autoload'])->toBeFalse();
});

it('still wants artisan for a desktop app', function () {
    expect(pushStubs(['target' => $this->dir])->getStatusCode())->toBe(1);
});
