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
        Schema::create('kpi_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');

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
            $table->decimal('throughput_per_day', 10, 2)->default(0);

            // Time Metrics
            $table->decimal('total_production_hours', 10, 2)->default(0);
            $table->decimal('total_downtime_hours', 10, 2)->default(0);
            $table->decimal('average_cycle_time', 10, 2)->default(0);

            // Quality Metrics
            $table->decimal('first_pass_yield', 5, 2)->default(0);
            $table->decimal('quality_rate', 5, 2)->default(0);
            $table->unsignedInteger('defect_count')->default(0);

            // Efficiency Metrics
            $table->decimal('oee', 5, 2)->default(0);
            $table->decimal('operator_efficiency', 5, 2)->default(0);
            $table->decimal('machine_utilization', 5, 2)->default(0);
            $table->decimal('capacity_utilization', 5, 2)->default(0);

            // Delivery Metrics
            $table->decimal('on_time_delivery_rate', 5, 2)->default(0);
            $table->unsignedInteger('orders_delivered')->default(0);
            $table->unsignedInteger('orders_delayed')->default(0);

            // Planning Metrics
            $table->decimal('bom_utilization_rate', 5, 2)->default(0);
            $table->decimal('so_to_wo_conversion_rate', 5, 2)->default(0);
            $table->decimal('planning_accuracy', 5, 2)->default(0);

            // Cost Metrics
            $table->decimal('scrap_cost', 12, 2)->default(0);
            $table->decimal('downtime_cost', 12, 2)->default(0);
            $table->decimal('labor_cost', 12, 2)->default(0);

            // Metadata
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['factory_id', 'summary_date'], 'unique_factory_date');
            $table->index(['factory_id', 'summary_date'], 'idx_factory_date');
            $table->index('summary_date', 'idx_date');
            $table->index('calculated_at', 'idx_calculated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_daily_summaries');
    }
};
