<?php

use Parcel\Application;
use Parcel\Command\SignCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

const SIGNING_ENV = ['PARCEL_SIGNING_KEY', 'PARCEL_SIGNING_KEY_ID', 'PARCEL_SIGNING_URL', 'PARCEL_SIGNING_TOKEN'];

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/parcel-sign-'.uniqid();
    mkdir($this->dir);
    $this->bundle = $this->dir.'/__nativephp_app_bundle.phar';
    file_put_contents($this->bundle, random_bytes(4096));

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $this->privateKey);

    $this->previousEnv = array_map(fn ($name) => getenv($name), array_combine(SIGNING_ENV, SIGNING_ENV));

    foreach (SIGNING_ENV as $name) {
        putenv($name);
    }
});

afterEach(function () {
    foreach ($this->previousEnv as $name => $value) {
        $value === false ? putenv($name) : putenv("{$name}={$value}");
    }

    if (isset($this->server)) {
        $this->server->stop();
    }

    exec('rm -rf '.escapeshellarg($this->dir));
});

function sign(string $bundle, array $options = []): CommandTester
{
    $tester = new CommandTester((new Application)->find('sign'));
    $tester->execute(['bundle' => $bundle, '--project' => 'project-uuid', ...$options]);

    return $tester;
}

function signatureVerifies(string $bundle, string $project = 'project-uuid'): bool
{
    $signed = json_decode(file_get_contents($bundle.'.sig'), true);
    $keys = json_decode(file_get_contents(dirname($bundle).'/keys.json'), true);
    $message = SignCommand::message($project, hash_file('sha256', $bundle), filesize($bundle));

    return SignCommand::verify($message, $signed['signature'], $keys[$signed['key_id']]);
}

function startSigningServer(object $test, array $env): string
{
    $port = random_int(20000, 40000);
    $test->server = new Process(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/../Fixtures/signing-server.php'],
        null,
        $env,
    );
    $test->server->start();
    $test->server->waitUntil(fn ($type, $output) => str_contains($output, 'started'));

    return "http://127.0.0.1:{$port}/sign";
}

it('signs with a local P-256 key, bound to the project', function () {
    putenv('PARCEL_SIGNING_KEY='.$this->privateKey);
    putenv('PARCEL_SIGNING_KEY_ID=dev-1');

    $tester = sign($this->bundle, ['--public-key-output' => $this->dir.'/keys.json']);

    expect($tester->getStatusCode())->toBe(0)
        ->and(json_decode(file_get_contents($this->bundle.'.sig'), true)['key_id'])->toBe('dev-1')
        ->and(signatureVerifies($this->bundle))->toBeTrue()
        ->and(signatureVerifies($this->bundle, 'another-project'))->toBeFalse();
});

it('accepts the key base64 encoded', function () {
    putenv('PARCEL_SIGNING_KEY='.base64_encode($this->privateKey));

    expect(sign($this->bundle, ['--public-key-output' => $this->dir.'/keys.json'])->getStatusCode())->toBe(0)
        ->and(signatureVerifies($this->bundle))->toBeTrue();
});

it('no longer verifies once the bundle changes', function () {
    putenv('PARCEL_SIGNING_KEY='.$this->privateKey);
    sign($this->bundle, ['--public-key-output' => $this->dir.'/keys.json']);

    $bytes = file_get_contents($this->bundle);
    $bytes[100] = chr(ord($bytes[100]) ^ 1);
    file_put_contents($this->bundle, $bytes);

    expect(signatureVerifies($this->bundle))->toBeFalse();
});

it('has Bifrost sign it when given a signing URL, sending only the hash', function () {
    putenv('PARCEL_SIGNING_URL='.startSigningServer($this, ['FIXTURE_TOKEN' => 'build-token', 'FIXTURE_KEY' => $this->privateKey]));
    putenv('PARCEL_SIGNING_TOKEN=build-token');

    $tester = sign($this->bundle, ['--public-key-output' => $this->dir.'/keys.json']);

    expect($tester->getStatusCode())->toBe(0, $tester->getDisplay())
        ->and(json_decode(file_get_contents($this->bundle.'.sig'), true)['key_id'])->toBe('bifrost-test')
        ->and(signatureVerifies($this->bundle))->toBeTrue();
});

it('refuses what a signing service returns when it does not match the bundle', function () {
    putenv('PARCEL_SIGNING_URL='.startSigningServer($this, ['FIXTURE_TOKEN' => 'build-token', 'FIXTURE_KEY' => $this->privateKey, 'FIXTURE_LIE' => '1']));
    putenv('PARCEL_SIGNING_TOKEN=build-token');

    expect(sign($this->bundle)->getStatusCode())->toBe(1)
        ->and($this->bundle.'.sig')->not->toBeFile();
});

it('fails when the signing service turns the token away', function () {
    putenv('PARCEL_SIGNING_URL='.startSigningServer($this, ['FIXTURE_TOKEN' => 'build-token', 'FIXTURE_KEY' => $this->privateKey]));
    putenv('PARCEL_SIGNING_TOKEN=wrong');

    $tester = sign($this->bundle);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('HTTP 401')
        ->and($this->bundle.'.sig')->not->toBeFile();
});

it('refuses to sign without a key', function () {
    expect(sign($this->bundle)->getStatusCode())->toBe(1)
        ->and($this->bundle.'.sig')->not->toBeFile();
});

it('refuses a key that is not P-256', function () {
    $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($rsa, $pem);
    putenv('PARCEL_SIGNING_KEY='.$pem);

    expect(sign($this->bundle)->getStatusCode())->toBe(1);
});

it('refuses to sign without a project', function () {
    putenv('PARCEL_SIGNING_KEY='.$this->privateKey);

    $tester = new CommandTester((new Application)->find('sign'));
    $tester->execute(['bundle' => $this->bundle]);

    expect($tester->getStatusCode())->toBe(1);
});
