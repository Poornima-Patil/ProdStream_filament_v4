<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's check if there are any existing dependency records that would conflict
        $duplicates = DB::select('
            SELECT predecessor_work_order_id, successor_work_order_id, COUNT(*) as count
            FROM work_order_dependencies
            GROUP BY predecessor_work_order_id, successor_work_order_id
            HAVING COUNT(*) > 1
        ');

        if (empty($duplicates)) {
            // No duplicates, safe to drop the unique constraint
            // Drop foreign keys that might be using this index
            Schema::table('work_order_dependencies', function (Blueprint $table) {
                $table->dropForeign(['predecessor_work_order_id']);
                $table->dropForeign(['successor_work_order_id']);
            });

            // Drop the unique constraint
            Schema::table('work_order_dependencies', function (Blueprint $table) {
                $table->dropUnique('unique_dependency');
            });

            // Re-add the foreign keys
            Schema::table('work_order_dependencies', function (Blueprint $table) {
                $table->foreign('predecessor_work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
                $table->foreign('successor_work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            });
        } else {
            throw new Exception('Cannot remove unique constraint: duplicate dependencies exist');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_dependencies', function (Blueprint $table) {
            $table->unique(['predecessor_work_order_id', 'successor_work_order_id'], 'unique_dependency');
        });
    }
};
