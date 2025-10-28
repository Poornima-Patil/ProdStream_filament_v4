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
        if (! Schema::hasTable('batch_key_consumptions')) {
            Schema::create('batch_key_consumptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('consumer_work_order_id')->constrained('work_orders')->onDelete('cascade');
                $table->foreignId('consumer_batch_id')->constrained('work_order_batches')->onDelete('cascade');
                $table->integer('consumer_batch_number');
                $table->foreignId('consumed_key_id')->constrained('work_order_batch_keys')->onDelete('cascade');
                $table->integer('quantity_consumed');
                $table->datetime('consumption_timestamp');
                $table->json('metadata')->nullable(); // Additional consumption details
                $table->timestamps();

                // Ensure unique consumption - one key can only be consumed once per batch
                $table->unique(['consumed_key_id', 'consumer_batch_id']);

                // Index for performance with custom names to avoid MySQL length limit
                $table->index(['consumer_work_order_id', 'consumer_batch_number'], 'bkc_consumer_wo_batch_idx');
                $table->index(['consumed_key_id'], 'bkc_consumed_key_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_key_consumptions');
    }
};
