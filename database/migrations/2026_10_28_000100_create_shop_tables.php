<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->index()->after('email');
            $table->string('city')->nullable()->after('phone');
            $table->string('address')->nullable()->after('city');
            $table->boolean('is_admin')->default(false)->after('address');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('in_menu')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hex', 7);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->string('type', 20)->default('vetement'); // vetement | pointure
            $table->string('guide')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('tagline')->nullable();
            $table->text('description')->nullable();
            $table->text('materials')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('compare_price')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_custom_possible')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('stock')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'color_id', 'size_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('phone', 20)->index();
            $table->string('city');
            $table->string('address')->nullable();
            $table->string('status', 20)->default('en_attente')->index();
            $table->unsignedInteger('total')->default(0);
            $table->text('admin_note')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('color_name')->nullable();
            $table->string('size_name')->nullable();
            $table->string('size_type', 20)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('total');
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity'); // + entrée / - sortie
            $table->integer('stock_after');
            $table->string('reason', 40); // reception_atelier, retour_client, correction, commande, annulation
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('categories');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'city', 'address', 'is_admin']));
    }
};
