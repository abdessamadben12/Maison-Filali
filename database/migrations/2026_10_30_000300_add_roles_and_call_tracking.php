<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles de l'équipe (gérant / confirmateur) et suivi des appels de confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // gerant = accès complet · confirmateur = société de confirmation (commandes à confirmer uniquement)
            $table->string('role', 20)->nullable()->after('is_admin');
        });
        DB::table('users')->where('is_admin', true)->update(['role' => 'gerant']);

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('handled_by_id')->nullable()->after('admin_note')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('call_attempts')->default(0)->after('handled_by_id');
            $table->timestamp('last_call_at')->nullable()->after('call_attempts');
            $table->index(['handled_by_id', 'status'], 'orders_handled_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_handled_status_idx');
            $table->dropConstrainedForeignId('handled_by_id');
            $table->dropColumn(['call_attempts', 'last_call_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
