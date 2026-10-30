<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $stock = 5): ProductVariant
    {
        $category = Category::create(['name' => 'Caftans']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Caftan test', 'price' => 100]);

        return $product->variants()->create(['stock' => $stock]);
    }

    private function order(ProductVariant $variant, int $quantity = 1): Order
    {
        $order = Order::create(['customer_name' => 'Cliente Test', 'phone' => '0612345678', 'city' => 'Casablanca', 'total' => 100 * $quantity]);
        $order->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => 'Caftan', 'quantity' => $quantity, 'unit_price' => 100, 'total' => 100 * $quantity]);

        return $order;
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Une transition invalide aurait dû être refusée.');
        } catch (RuntimeException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_single_variant_checkout_works_when_variant_id_is_omitted(): void
    {
        $variant = $this->variant();
        $this->post('/commande', ['product_id' => $variant->product_id, 'quantity' => 1,
            'name' => 'Cliente Test', 'phone' => '06 12 34 56 78', 'city' => 'Casablanca'])
            ->assertRedirect(route('order.thanks', Order::firstOrFail()));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(5, $variant->fresh()->stock); // Stock is deducted at confirmation.
        $this->get(route('order.thanks', Order::first()))->assertOk();
    }

    public function test_checkout_rejects_unavailable_stock(): void
    {
        $variant = $this->variant(0);
        $this->postJson('/commande', ['product_id' => $variant->product_id, 'quantity' => 1,
            'name' => 'Cliente Test', 'phone' => '0612345678', 'city' => 'Casablanca'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('orders', 0);
    }

    public static function invalidPhoneEndpoints(): array
    {
        return [['/commande'], ['/contact'], ['/inscription']];
    }

    #[DataProvider('invalidPhoneEndpoints')]
    public function test_malformed_phone_returns_validation_instead_of_type_error(string $endpoint): void
    {
        $this->postJson($endpoint, ['phone' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_stale_confirmation_cannot_deduct_stock_twice(): void
    {
        $variant = $this->variant();
        $order = $this->order($variant, 2);
        $stale = $order->fresh();
        $order->confirm();
        $this->rejects(fn () => $stale->confirm());
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_stale_cancellation_cannot_restore_stock_twice(): void
    {
        $variant = $this->variant();
        $order = $this->order($variant, 2);
        $order->confirm();
        $stale = $order->fresh();
        $order->cancel();
        $this->rejects(fn () => $stale->cancel());
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_competing_orders_cannot_make_stock_negative(): void
    {
        $variant = $this->variant(1);
        $first = $this->order($variant);
        $second = $this->order($variant);
        $second->load('items.variant');
        $first->confirm();
        $this->rejects(fn () => $second->confirm());
        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertSame('en_attente', $second->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_partial_stock_deductions_are_rolled_back_if_a_later_line_fails(): void
    {
        $variant = $this->variant(5);
        $order = $this->order($variant, 3);
        $line = $order->items()->first()->replicate();
        $line->save();
        $this->rejects(fn () => $order->confirm());
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame('en_attente', $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_deleted_variant_is_reported_without_a_null_dereference(): void
    {
        $variant = $this->variant();
        $order = $this->order($variant);
        $variant->delete();
        $this->rejects(fn () => $variant->adjustStock(1, 'correction'));
        $this->rejects(fn () => $order->confirm());
        $this->assertSame('en_attente', $order->fresh()->status);
    }

    public function test_stale_shipping_cannot_overwrite_cancellation(): void
    {
        $variant = $this->variant();
        $order = $this->order($variant);
        $order->confirm();
        $stale = $order->fresh();
        $order->cancel();
        $this->rejects(fn () => $stale->ship());
        $this->assertSame('annulee', $order->fresh()->status);
    }
}
