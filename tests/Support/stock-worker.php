<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1]]);
Illuminate\Support\Facades\DB::purge('sqlite');
try {
    // Both workers load the old state before the barrier is released.
    $order = App\Models\Order::with('items.variant')->findOrFail($argv[2]);
    echo "READY\n";
    $deadline = microtime(true) + 15;
    while (! is_file($argv[3]) && microtime(true) < $deadline) {
        usleep(10000);
        clearstatcache(true, $argv[3]);
    }
    if (! is_file($argv[3])) {
        throw new LogicException('Stock test barrier timed out.');
    }
    $order->{$argv[4]}();
    echo "APPLIED\n";
} catch (Illuminate\Database\QueryException $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
} catch (RuntimeException $exception) {
    echo "REJECTED\n";
}
