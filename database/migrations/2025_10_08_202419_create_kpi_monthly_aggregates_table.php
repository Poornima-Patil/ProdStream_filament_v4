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
        Schema::create('kpi_monthly_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->unsignedTinyInteger('month');

            // Aggregated from daily summaries
            $table->decimal('avg_completion_rate', 5, 2)->default(0);
            $table->decimal('avg_throughput', 10, 2)->default(0);
            $table->decimal('avg_quality_rate', 5, 2)->default(0);
            $table->decimal('avg_oee', 5, 2)->default(0);

            // Totals
            $table->unsignedInteger('total_units_produced')->default(0);
            $table->unsignedInteger('total_work_orders')->default(0);
            $table->decimal('total_production_hours', 12, 2)->default(0);
            $table->decimal('total_downtime_hours', 12, 2)->default(0);

            // Strategic Metrics
            $table->decimal('capacity_utilization', 5, 2)->default(0);
            $table->decimal('planning_efficiency_score', 5, 2)->default(0);
            $table->decimal('customer_satisfaction_score', 5, 2)->default(0);

            // Financial
            $table->decimal('total_scrap_cost', 12, 2)->default(0);
            $table->decimal('total_labor_cost', 12, 2)->default(0);
            $table->decimal('revenue_per_hour', 12, 2)->default(0);

            // Metadata
            $table->boolean('is_finalized')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['factory_id', 'year', 'month'], 'unique_factory_year_month');
            $table->index(['factory_id', 'year', 'month'], 'idx_factory_date');
            $table->index(['year', 'month'], 'idx_year_month');
            $table->index('is_finalized', 'idx_finalized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_monthly_aggregates');
    }
};
