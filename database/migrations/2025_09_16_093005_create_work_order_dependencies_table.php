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
        Schema::create('work_order_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_group_id')->constrained('work_order_groups')->onDelete('cascade');
            $table->foreignId('predecessor_work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('successor_work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->integer('required_quantity'); // Quantity needed from predecessor to start successor
            $table->enum('dependency_type', ['quantity_based', 'completion_based'])->default('quantity_based');
            $table->boolean('is_satisfied')->default(false);
            $table->datetime('satisfied_at')->nullable();
            $table->json('conditions')->nullable(); // For future expansion of dependency conditions
            $table->timestamps();

            // Ensure unique dependencies between work orders
            $table->unique(['predecessor_work_order_id', 'successor_work_order_id'], 'unique_dependency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_dependencies');
    }
};
