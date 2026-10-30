<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pour accélérer les requêtes fréquentes du site et de l'admin :
 * listes de produits (catégorie, nouveautés, prix), filtres couleur / taille,
 * alertes de stock, commandes par statut / date, historique de stock, messages.
 */
return new class extends Migration
{
    /** table => [nom de l'index => colonnes] */
    private array $indexes = [
        'categories' => [
            'categories_active_position_idx' => ['is_active', 'position'],
        ],
        'products' => [
            'products_active_category_position_idx' => ['is_active', 'category_id', 'position'],
            'products_active_new_position_idx' => ['is_active', 'is_new', 'position'],
            'products_featured_idx' => ['is_featured', 'position'],
            'products_price_idx' => ['price'],
        ],
        'product_variants' => [
            'variants_color_stock_idx' => ['color_id', 'stock'],
            'variants_size_stock_idx' => ['size_id', 'stock'],
            'variants_stock_idx' => ['stock'],
        ],
        'orders' => [
            'orders_status_created_idx' => ['status', 'created_at'],
            'orders_created_idx' => ['created_at'],
            'orders_user_created_idx' => ['user_id', 'created_at'],
            'orders_customer_created_idx' => ['customer_id', 'created_at'],
        ],
        'order_items' => [
            'order_items_order_product_idx' => ['order_id', 'product_id'],
        ],
        'stock_movements' => [
            'stock_movements_variant_created_idx' => ['product_variant_id', 'created_at'],
        ],
        'contact_messages' => [
            'contact_messages_read_created_idx' => ['is_read', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $name => $columns) {
                    $t->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach (array_keys($indexes) as $name) {
                    $t->dropIndex($name);
                }
            });
        }
    }
};
