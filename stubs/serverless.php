<?php

use App\Native\Request;
use Illuminate\Contracts\Http\Kernel;

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture(...array_slice($argv, 1))
);

$kernel->terminate($request, $response);

echo $response->headers."\r\n\n\r\n\n";

$response->sendContent();
