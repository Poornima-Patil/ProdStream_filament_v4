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
        Schema::create('work_order_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->integer('batch_number'); // 1, 2, 3, 4...
            $table->integer('planned_quantity');
            $table->integer('actual_quantity')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'failed'])->default('planned');
            $table->json('keys_required')->nullable(); // Array of required key types
            $table->json('keys_consumed')->nullable(); // Array of consumed keys
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();

            // Ensure unique batch numbers per work order
            $table->unique(['work_order_id', 'batch_number']);

            // Index for performance
            $table->index(['work_order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_batches');
    }
};
