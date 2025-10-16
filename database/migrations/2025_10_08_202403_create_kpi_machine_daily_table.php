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
        Schema::create('kpi_machine_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');

            // Utilization Metrics
            $table->decimal('utilization_rate', 5, 2)->default(0);
            $table->decimal('uptime_hours', 10, 2)->default(0);
            $table->decimal('downtime_hours', 10, 2)->default(0);
            $table->decimal('planned_downtime_hours', 10, 2)->default(0);
            $table->decimal('unplanned_downtime_hours', 10, 2)->default(0);

            // Production Metrics
            $table->unsignedInteger('units_produced')->default(0);
            $table->unsignedInteger('work_orders_completed')->default(0);
            $table->decimal('average_cycle_time', 10, 2)->default(0);

            // Quality Metrics
            $table->decimal('quality_rate', 5, 2)->default(0);
            $table->decimal('scrap_rate', 5, 2)->default(0);
            $table->decimal('first_pass_yield', 5, 2)->default(0);

            // Performance Metrics
            $table->decimal('machine_performance_index', 5, 2)->default(0);
            $table->decimal('machine_reliability_score', 5, 2)->default(0);
            $table->decimal('availability_rate', 5, 2)->default(0);

            // Maintenance Metrics
            $table->decimal('mtbf', 10, 2)->default(0);
            $table->decimal('mttr', 10, 2)->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            // Metadata
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['factory_id', 'machine_id', 'summary_date'], 'unique_factory_machine_date');
            $table->index(['factory_id', 'summary_date'], 'idx_factory_date');
            $table->index(['machine_id', 'summary_date'], 'idx_machine_date');
            $table->index('summary_date', 'idx_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_machine_daily');
    }
};
