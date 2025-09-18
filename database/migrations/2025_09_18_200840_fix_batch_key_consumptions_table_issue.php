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
            Schema::table('batch_key_consumptions', function (Blueprint $table) {
                // Add missing indexes if they don't exist
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('batch_key_consumptions');

                if (!isset($indexes['bkc_consumer_wo_batch_idx'])) {
                    $table->index(['consumer_work_order_id', 'consumer_batch_number'], 'bkc_consumer_wo_batch_idx');
                }

                if (!isset($indexes['bkc_consumed_key_idx'])) {
                    $table->index(['consumed_key_id'], 'bkc_consumed_key_idx');
                }

                // Add missing unique constraint if it doesn't exist
                if (!isset($indexes['batch_key_consumptions_consumed_key_id_consumer_batch_id_unique'])) {
                    $table->unique(['consumed_key_id', 'consumer_batch_id']);
                }

                // Add missing foreign keys if they don't exist
                $foreignKeys = $sm->listTableForeignKeys('batch_key_consumptions');
                $foreignKeyNames = array_map(fn($fk) => $fk->getName(), $foreignKeys);

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
