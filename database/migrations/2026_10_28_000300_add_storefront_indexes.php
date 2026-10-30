<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'is_new', 'position', 'id'], 'products_listing_index');
            $table->index(['category_id', 'is_active', 'price'], 'products_category_price_index');
            $table->index(['is_active', 'price', 'id'], 'products_price_index');
            $table->index(['is_active', 'is_featured', 'position'], 'products_featured_index');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->index(['user_id', 'id'], 'orders_account_index'));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_listing_index');
            $table->dropIndex('products_category_price_index');
            $table->dropIndex('products_price_index');
            $table->dropIndex('products_featured_index');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_account_index'));
    }
};
