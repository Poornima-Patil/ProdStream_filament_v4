<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove global unique constraints and add factory-scoped constraints

        // Work Orders: Remove global unique_id constraint, add composite constraint
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropUnique(['unique_id']);
            $table->unique(['unique_id', 'factory_id'], 'work_orders_unique_id_factory_unique');
        });

        // BOMs: Remove global unique_id constraint, add composite constraint
        Schema::table('boms', function (Blueprint $table) {
            $table->dropUnique(['unique_id']);
            $table->unique(['unique_id', 'factory_id'], 'boms_unique_id_factory_unique');
        });

        // Work Order Groups: Remove global unique_id constraint, add composite constraint
        Schema::table('work_order_groups', function (Blueprint $table) {
            $table->dropUnique(['unique_id']);
            $table->unique(['unique_id', 'factory_id'], 'work_order_groups_unique_id_factory_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore global unique constraints (remove factory-scoped constraints)

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropUnique('work_orders_unique_id_factory_unique');
            $table->unique('unique_id');
        });

        Schema::table('boms', function (Blueprint $table) {
            $table->dropUnique('boms_unique_id_factory_unique');
            $table->unique('unique_id');
        });

        Schema::table('work_order_groups', function (Blueprint $table) {
            $table->dropUnique('work_order_groups_unique_id_factory_unique');
            $table->unique('unique_id');
        });
    }
};
