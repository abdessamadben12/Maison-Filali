<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu du haut limité à config('shop.menu_max') catégories :
 * on garde les premières (ordre d'affichage) et on décoche les autres.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keep = DB::table('categories')->where('in_menu', true)->orderBy('position')->orderBy('name')
            ->limit((int) config('shop.menu_max', 2))->pluck('id');
        DB::table('categories')->where('in_menu', true)->whereNotIn('id', $keep)->update(['in_menu' => false]);
    }

    public function down(): void
    {
        //
    }
};
