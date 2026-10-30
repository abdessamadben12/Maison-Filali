<?php

// Read-only, in-process benchmark of the local storefront (no HTTP server needed).
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$queries = 0;
Illuminate\Support\Facades\DB::listen(function () use (&$queries) { $queries++; });
foreach (['/', '/catalogue', '/catalogue?categories[]=caftans&categories[]=takchitas', '/connexion'] as $path) {
    $queries = 0;
    $started = hrtime(true);
    $request = Illuminate\Http\Request::create($path);
    $response = $kernel->handle($request);
    printf("%s status=%d queries=%d time=%.1fms html=%d bytes peak=%.1fMB\n", $path,
        $response->getStatusCode(), $queries, (hrtime(true) - $started) / 1e6,
        strlen($response->getContent()), memory_get_peak_usage(true) / 1048576);
    $kernel->terminate($request, $response);
}
