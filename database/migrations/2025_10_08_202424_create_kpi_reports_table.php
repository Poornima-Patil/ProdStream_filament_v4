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
        Schema::create('kpi_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 50);
            $table->date('report_date');

            // File Information
            $table->string('file_path', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_format', 20)->nullable();

            // Report Content
            $table->unsignedInteger('kpi_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);

            // Generation Info
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('generation_completed_at')->nullable();
            $table->unsignedInteger('generation_duration_seconds')->nullable();

            // Delivery Info
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->json('recipients')->nullable();

            // Status
            $table->string('status', 50)->default('pending');
            $table->text('error_message')->nullable();

            // Metadata
            $table->timestamps();

            // Indexes
            $table->index(['factory_id', 'report_type', 'report_date'], 'idx_factory_type_date');
            $table->index(['factory_id', 'status'], 'idx_factory_status');
            $table->index('report_date', 'idx_report_date');
            $table->index('generation_completed_at', 'idx_generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_reports');
    }
};
