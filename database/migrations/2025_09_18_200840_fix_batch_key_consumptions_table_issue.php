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
        // Check if table exists from failed migration and complete it
        if (Schema::hasTable('batch_key_consumptions')) {
            // Use raw queries to check for existing indexes and constraints in Laravel 12
            $connection = Schema::getConnection();

            // Check for existing indexes using information_schema
            $existingIndexes = $connection->select("
                SELECT INDEX_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'batch_key_consumptions'
            ", [$connection->getDatabaseName()]);

            $indexNames = array_column($existingIndexes, 'INDEX_NAME');

            Schema::table('batch_key_consumptions', function (Blueprint $table) use ($indexNames) {
                // Add missing indexes if they don't exist
                if (!in_array('bkc_consumer_wo_batch_idx', $indexNames)) {
                    $table->index(['consumer_work_order_id', 'consumer_batch_number'], 'bkc_consumer_wo_batch_idx');
                }

                if (!in_array('bkc_consumed_key_idx', $indexNames)) {
                    $table->index(['consumed_key_id'], 'bkc_consumed_key_idx');
                }

                // Add missing unique constraint if it doesn't exist
                if (!in_array('batch_key_consumptions_consumed_key_id_consumer_batch_id_unique', $indexNames)) {
                    $table->unique(['consumed_key_id', 'consumer_batch_id']);
                }
            });

            // Check for existing foreign keys
            $existingForeignKeys = $connection->select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'batch_key_consumptions'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$connection->getDatabaseName()]);

            $foreignKeyNames = array_column($existingForeignKeys, 'CONSTRAINT_NAME');

            Schema::table('batch_key_consumptions', function (Blueprint $table) use ($foreignKeyNames) {
                // Add missing foreign keys if they don't exist
                if (!in_array('batch_key_consumptions_consumer_work_order_id_foreign', $foreignKeyNames)) {
                    $table->foreign('consumer_work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
                }

                if (!in_array('batch_key_consumptions_consumer_batch_id_foreign', $foreignKeyNames)) {
                    $table->foreign('consumer_batch_id')->references('id')->on('work_order_batches')->onDelete('cascade');
                }

                if (!in_array('batch_key_consumptions_consumed_key_id_foreign', $foreignKeyNames)) {
                    $table->foreign('consumed_key_id')->references('id')->on('work_order_batch_keys')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to do anything on rollback
    }
};
