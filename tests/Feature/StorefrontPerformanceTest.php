<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['name' => 'Caftans', 'in_menu' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Caftan test', 'price' => 100]);
        $product->variants()->create(['stock' => 5]);

        return $product;
    }

    public function test_catalogue_query_count_stays_bounded_as_the_catalogue_grows(): void
    {
        $product = $this->product();
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->get('/catalogue')->assertOk();
        $smallCount = count($queries);

        for ($i = 0; $i < 50; $i++) {
            $copy = $product->replicate(['slug']);
            $copy->name = 'Caftan '.$i;
            $copy->save();
            $copy->variants()->create(['stock' => 2]);
        }
        $queries = [];
        $this->get('/catalogue')->assertOk()->assertViewHas('products', fn ($products) => $products->count() === 12 && $products->total() === 51);
        $this->assertLessThanOrEqual($smallCount, count($queries));
        $this->assertLessThanOrEqual(10, count($queries));
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_starts_with($sql, 'select "id" from "products"')));
    }

    public function test_color_and_size_must_match_the_same_available_variant(): void
    {
        $product = $this->product();
        $red = Color::create(['name' => 'Rouge', 'hex' => '#ff0000']);
        $blue = Color::create(['name' => 'Bleu', 'hex' => '#0000ff']);
        $small = Size::create(['name' => 'S']);
        $large = Size::create(['name' => 'L']);
        $product->variants()->create(['color_id' => $red->id, 'size_id' => $small->id, 'stock' => 2]);
        $product->variants()->create(['color_id' => $blue->id, 'size_id' => $large->id, 'stock' => 2]);
        $this->get('/catalogue?couleurs[]='.$red->id.'&tailles[]='.$large->id.'&stock=1')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
        $this->get('/catalogue?couleurs[]='.$red->id.'&tailles[]='.$small->id.'&stock=1')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 1);
    }

    public static function malformedFilters(): array
    {
        return array_map(fn ($query) => [$query], [
            'q[]=bad', 'categories[0][]=bad', 'couleurs[0][]=1', 'tailles[]=bad',
            'prix_min[]=10', 'prix_max=-1', 'stock[]=1', 'page[]=2', 'page=999999999999',
            'tri[]=nom', 'q='.str_repeat('a', 151),
        ]);
    }

    #[DataProvider('malformedFilters')]
    public function test_invalid_catalogue_filters_are_rejected_without_a_server_error(string $query): void
    {
        $this->get('/catalogue?'.$query)->assertStatus(422);
    }

    public function test_invalid_search_query_does_not_break_other_pages(): void
    {
        $this->get('/?q[]=bad')->assertOk();
    }

    public function test_menu_reflects_changes_on_the_next_request(): void
    {
        $product = $this->product();
        $this->get('/contact')->assertOk()->assertSee('Caftans');
        $product->category->update(['name' => 'Nouvelle collection']);
        $this->get('/contact')->assertOk()->assertSee('Nouvelle collection')->assertDontSee('>Caftans<', false);
    }

    public function test_product_and_featured_fallback_render_with_variants(): void
    {
        $product = $this->product();
        $this->get('/')->assertOk();
        $this->get(route('product', $product))->assertOk()->assertSee('Caftan test');
        $product->category->update(['is_active' => false]);
        $this->get(route('product', $product))->assertNotFound();
    }

    public function test_account_orders_are_paginated_and_current_order_is_not_lost(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            Order::create(['user_id' => $user->id, 'customer_name' => 'Cliente', 'phone' => '0612345678', 'city' => 'Casablanca', 'status' => $i === 0 ? 'en_attente' : 'livree']);
        }
        $this->actingAs($user)->get('/mon-compte')->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 20 && $orders->total() === 25)
            ->assertViewHas('current', fn ($order) => $order->status === 'en_attente');
        $this->get('/mon-compte?page=2')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 5);
    }
}
