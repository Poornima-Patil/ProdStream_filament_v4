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
        Schema::create('work_order_group_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_group_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // 'status_change', 'dependency_satisfied', 'work_order_triggered'
            $table->string('event_description');
            $table->foreignId('triggered_work_order_id')->nullable()->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('triggering_work_order_id')->nullable()->constrained('work_orders')->onDelete('cascade');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->json('metadata')->nullable(); // Store additional context like dependency details
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['work_order_group_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_group_logs');
    }
};
