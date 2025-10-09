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
        Schema::create('kpi_operator_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');

            // Production Metrics
            $table->unsignedInteger('work_orders_completed')->default(0);
            $table->unsignedInteger('work_orders_assigned')->default(0);
            $table->unsignedInteger('units_produced')->default(0);
            $table->decimal('hours_worked', 10, 2)->default(0);

            // Efficiency Metrics
            $table->decimal('efficiency_rate', 5, 2)->default(0);
            $table->decimal('productivity_score', 5, 2)->default(0);
            $table->decimal('average_cycle_time', 10, 2)->default(0);

            // Quality Metrics
            $table->decimal('quality_rate', 5, 2)->default(0);
            $table->decimal('first_pass_yield', 5, 2)->default(0);
            $table->unsignedInteger('defect_count')->default(0);
            $table->unsignedInteger('scrap_units')->default(0);

            // Skill Metrics
            $table->string('skill_level', 50)->nullable();
            $table->decimal('proficiency_score', 5, 2)->default(0);
            $table->decimal('training_hours', 10, 2)->default(0);

            // Workload Metrics
            $table->decimal('workload_balance_score', 5, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);

            // Metadata
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['factory_id', 'operator_id', 'summary_date'], 'unique_factory_operator_date');
            $table->index(['factory_id', 'summary_date'], 'idx_factory_date');
            $table->index(['operator_id', 'summary_date'], 'idx_operator_date');
            $table->index('summary_date', 'idx_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_operator_daily');
    }
};
