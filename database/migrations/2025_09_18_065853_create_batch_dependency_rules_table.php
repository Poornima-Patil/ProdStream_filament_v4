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
        Schema::create('batch_dependency_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_group_id')->constrained('work_order_groups')->onDelete('cascade');
            $table->foreignId('successor_work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('predecessor_work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->integer('keys_required_per_batch')->default(1); // How many keys needed per batch
            $table->integer('batch_size'); // Quantity per batch
            $table->enum('rule_type', ['simple', 'cumulative'])->default('simple');
            $table->json('metadata')->nullable(); // Additional rule configuration
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index for performance
            $table->index(['work_order_group_id', 'successor_work_order_id']);
            $table->index(['successor_work_order_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_dependency_rules');
    }
};
