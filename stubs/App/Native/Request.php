<?php

namespace App\Native;

use Illuminate\Http\Request as IlluminateRequest;

class Request extends IlluminateRequest
{
    public static function capture()
    {
        $args = func_get_args();

        return tap(parent::capture(), fn ($request) => $request->hydrateFromCli(...$args));
    }

    public function hydrateFromCli(string $method, string $uri, ?string $data = null, ?string $headers = null)
    {
        if ($headers != '') {
            $this->request->headers = json_decode($data, true);
        }

        if ($data != '') {
            $this->request->add(json_decode($data, true));
        }

        $this->server->add([
            'REMOTE_ADDR' => '0.0.0.0',
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => $method,
        ]);
    }

    public function getScheme(): string
    {
        return 'php';
    }

    public function getSchemeAndHttpHost(): string
    {
        return 'php://localhost/';
    }
}
