<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offres (packs) : plusieurs produits vendus ensemble à un prix de pack,
 * définis par le gérant dans l'admin (Offres / packs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('compare_price')->nullable(); // vide = somme des prix des produits
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(true); // affiché sur l'accueil
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });

        Schema::create('pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('pack_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
            $table->string('pack_name')->nullable()->after('pack_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pack_id');
            $table->dropColumn('pack_name');
        });
        Schema::dropIfExists('pack_items');
        Schema::dropIfExists('packs');
    }
};
