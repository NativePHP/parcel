<?php

use Parcel\Application;
use Parcel\Command\SignCommand;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/parcel-sign-'.uniqid();
    mkdir($this->dir);
    $this->bundle = $this->dir.'/__nativephp_app_bundle.phar';
    file_put_contents($this->bundle, random_bytes(4096));

    $this->keypair = sodium_crypto_sign_keypair();
    $this->previousKey = getenv('PARCEL_SIGNING_KEY');
});

afterEach(function () {
    $this->previousKey === false ? putenv('PARCEL_SIGNING_KEY') : putenv('PARCEL_SIGNING_KEY='.$this->previousKey);
    exec('rm -rf '.escapeshellarg($this->dir));
});

function sign(string $bundle, array $options = []): CommandTester
{
    $tester = new CommandTester((new Application)->find('sign'));
    $tester->execute(['bundle' => $bundle, ...$options]);

    return $tester;
}

function verifies(string $bundle, string $keypair): bool
{
    return sodium_crypto_sign_verify_detached(
        base64_decode(file_get_contents($bundle.'.sig')),
        SignCommand::message($bundle),
        sodium_crypto_sign_publickey($keypair),
    );
}

it('writes a signature the public key verifies', function () {
    putenv('PARCEL_SIGNING_KEY='.base64_encode(sodium_crypto_sign_secretkey($this->keypair)));

    $tester = sign($this->bundle);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain(base64_encode(sodium_crypto_sign_publickey($this->keypair)))
        ->and(verifies($this->bundle, $this->keypair))->toBeTrue();
});

it('writes the public key to a file when asked', function () {
    putenv('PARCEL_SIGNING_KEY='.base64_encode(sodium_crypto_sign_secretkey($this->keypair)));

    sign($this->bundle, ['--public-key-output' => $this->dir.'/public.key']);

    expect(file_get_contents($this->dir.'/public.key'))->toBe(base64_encode(sodium_crypto_sign_publickey($this->keypair)));
});

it('accepts a 32 byte seed', function () {
    $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
    putenv('PARCEL_SIGNING_KEY='.base64_encode($seed));

    expect(sign($this->bundle)->getStatusCode())->toBe(0)
        ->and(verifies($this->bundle, sodium_crypto_sign_seed_keypair($seed)))->toBeTrue();
});

it('no longer verifies once the bundle changes', function () {
    putenv('PARCEL_SIGNING_KEY='.base64_encode(sodium_crypto_sign_secretkey($this->keypair)));
    sign($this->bundle);

    $bytes = file_get_contents($this->bundle);
    $bytes[100] = chr(ord($bytes[100]) ^ 1);
    file_put_contents($this->bundle, $bytes);

    expect(verifies($this->bundle, $this->keypair))->toBeFalse();
});

it('refuses to sign without a usable key', function (string|false $key) {
    $key === false ? putenv('PARCEL_SIGNING_KEY') : putenv('PARCEL_SIGNING_KEY='.$key);

    expect(sign($this->bundle)->getStatusCode())->toBe(1)
        ->and($this->bundle.'.sig')->not->toBeFile();
})->with([
    'missing' => [false],
    'not base64' => ['%%%'],
    'wrong length' => [base64_encode('short')],
]);
