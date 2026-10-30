<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [['confirm', false], ['confirm', true], ['cancel', true]];
    }

    #[DataProvider('races')]
    public function test_simultaneous_stock_transitions(string $action, bool $sameOrder): void
    {
        $database = tempnam(sys_get_temp_dir(), 'filali-stock-');
        $barrier = $database.'.ready';
        $processes = [];
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $category = Category::create(['name' => 'Concurrence']);
            $product = Product::create(['category_id' => $category->id, 'name' => 'Dernière pièce', 'price' => 100]);
            $variant = $product->variants()->create(['stock' => 1]);
            $makeOrder = function () use ($variant, $product) {
                $order = Order::create(['customer_name' => 'Cliente Test', 'phone' => '0612345678', 'city' => 'Casablanca', 'total' => 100]);
                $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'total' => 100]);
                return $order;
            };
            $first = $makeOrder();
            $second = $sameOrder ? $first : $makeOrder();
            if ($action === 'cancel') {
                $first->confirm();
            }
            foreach ([$first, $second] as $order) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/stock-worker.php'), $database, (string) $order->id, $barrier, $action], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')), $process->getErrorOutput());
            }
            touch($barrier);
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
            $output = implode('', array_map(fn ($process) => $process->getOutput(), $processes));
            $this->assertSame(1, substr_count($output, 'APPLIED'));
            $this->assertSame(1, substr_count($output, 'REJECTED'));
            $this->assertSame($action === 'cancel' ? 1 : 0, $variant->fresh()->stock);
            $this->assertDatabaseCount('stock_movements', $action === 'cancel' ? 2 : 1);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::purge('sqlite');
            foreach ([$database, $barrier, $database.'-journal', $database.'-wal', $database.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
