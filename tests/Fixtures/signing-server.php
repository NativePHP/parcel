<?php

// A stand-in for Bifrost's signing endpoint, served with `php -S`.
// FIXTURE_TOKEN is the build token it accepts, FIXTURE_KEY the PEM key it
// signs with, and FIXTURE_LIE=1 makes it sign the wrong thing.

$key = openssl_pkey_get_private((string) getenv('FIXTURE_KEY'));

if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer '.getenv('FIXTURE_TOKEN')) {
    http_response_code(401);
    echo '{"message":"Unauthenticated."}';

    return;
}

$request = json_decode((string) file_get_contents('php://input'), true);
$message = implode("\n", [
    'nativephp-parcel-bundle-v1',
    $request['project'],
    getenv('FIXTURE_LIE') ? str_repeat('0', 64) : $request['sha256'],
    (string) $request['size'],
]);

openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);

header('Content-Type: application/json');
echo json_encode([
    'key_id' => 'bifrost-test',
    'signature' => base64_encode($signature),
    'public_key' => preg_replace('/-----[^-]+-----|\s+/', '', openssl_pkey_get_details($key)['key']),
]);
