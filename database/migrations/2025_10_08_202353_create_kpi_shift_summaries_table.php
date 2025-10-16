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
        Schema::create('kpi_shift_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('shift_date');
            $table->string('shift_name', 50);
            $table->dateTime('shift_start_time');
            $table->dateTime('shift_end_time');

            // Work Order Metrics
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedInteger('in_progress_orders')->default(0);
            $table->unsignedInteger('assigned_orders')->default(0);
            $table->unsignedInteger('hold_orders')->default(0);
            $table->unsignedInteger('closed_orders')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);

            // Production Metrics
            $table->unsignedInteger('total_units_produced')->default(0);
            $table->unsignedInteger('ok_units')->default(0);
            $table->unsignedInteger('scrapped_units')->default(0);
            $table->decimal('scrap_rate', 5, 2)->default(0);
            $table->decimal('throughput_per_hour', 10, 2)->default(0);

            // Time Metrics
            $table->decimal('total_production_hours', 10, 2)->default(0);
            $table->decimal('total_downtime_hours', 10, 2)->default(0);
            $table->decimal('average_cycle_time', 10, 2)->default(0);

            // Quality Metrics
            $table->decimal('first_pass_yield', 5, 2)->default(0);
            $table->decimal('quality_rate', 5, 2)->default(0);
            $table->unsignedInteger('defect_count')->default(0);

            // Efficiency Metrics
            $table->decimal('operator_efficiency', 5, 2)->default(0);
            $table->decimal('machine_utilization', 5, 2)->default(0);
            $table->decimal('schedule_adherence', 5, 2)->default(0);

            // Planning Metrics
            $table->decimal('setup_time_hours', 10, 2)->default(0);
            $table->unsignedInteger('changeover_count')->default(0);
            $table->decimal('changeover_efficiency', 5, 2)->default(0);

            // Metadata
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['factory_id', 'shift_id'], 'unique_factory_shift');
            $table->index(['factory_id', 'shift_date'], 'idx_factory_shift_date');
            $table->index('shift_date', 'idx_shift_date');
            $table->index('calculated_at', 'idx_calculated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_shift_summaries');
    }
};
