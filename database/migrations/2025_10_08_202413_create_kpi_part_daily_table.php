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
        Schema::create('kpi_part_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_number_id')->constrained('part_numbers')->cascadeOnDelete();
            $table->date('summary_date');

            // Production Metrics
            $table->unsignedInteger('units_produced')->default(0);
            $table->unsignedInteger('work_orders_count')->default(0);
            $table->decimal('production_volume_percentage', 5, 2)->default(0);

            // Quality Metrics
            $table->decimal('quality_rate', 5, 2)->default(0);
            $table->decimal('scrap_rate', 5, 2)->default(0);
            $table->decimal('first_pass_yield', 5, 2)->default(0);
            $table->unsignedInteger('defect_count')->default(0);

            // Time Metrics
            $table->decimal('average_cycle_time', 10, 2)->default(0);
            $table->decimal('average_lead_time', 10, 2)->default(0);

            // Fulfillment Metrics
            $table->decimal('fulfillment_rate', 5, 2)->default(0);
            $table->decimal('on_time_completion_rate', 5, 2)->default(0);

            // Metadata
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['factory_id', 'part_number_id', 'summary_date'], 'unique_factory_part_date');
            $table->index(['factory_id', 'summary_date'], 'idx_factory_date');
            $table->index(['part_number_id', 'summary_date'], 'idx_part_date');
            $table->index('summary_date', 'idx_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_part_daily');
    }
};
