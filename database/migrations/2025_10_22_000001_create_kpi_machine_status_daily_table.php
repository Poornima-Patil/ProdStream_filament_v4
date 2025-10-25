<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_machine_status_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->unsignedInteger('running_count')->default(0);
            $table->unsignedInteger('setup_count')->default(0);
            $table->unsignedInteger('hold_count')->default(0);
            $table->unsignedInteger('scheduled_count')->default(0);
            $table->unsignedInteger('idle_count')->default(0);
            $table->unsignedInteger('total_machines')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['factory_id', 'summary_date'], 'unique_factory_machine_status_date');
            $table->index(['factory_id', 'summary_date'], 'idx_factory_machine_status_date');
            $table->index('summary_date', 'idx_machine_status_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_machine_status_daily');
    }
};
