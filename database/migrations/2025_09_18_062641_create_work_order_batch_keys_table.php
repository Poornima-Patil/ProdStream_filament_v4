<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_order_batch_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('batch_id')->constrained('work_order_batches')->onDelete('cascade');
            $table->integer('batch_number');
            $table->string('key_code')->unique(); // KEY-W1-001-20250918
            $table->integer('quantity_produced');
            $table->datetime('generated_at');
            $table->boolean('is_consumed')->default(false);
            $table->datetime('consumed_at')->nullable();
            $table->foreignId('consumed_by_work_order_id')->nullable()->constrained('work_orders')->onDelete('set null');
            $table->integer('consumed_by_batch_number')->nullable();
            $table->timestamps();

            // Ensure only grouped work orders can have batch keys
            $table->index(['work_order_id', 'is_consumed']);
            $table->index(['is_consumed', 'work_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_batch_keys');
    }
};
