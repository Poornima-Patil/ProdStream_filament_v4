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
        // Work Orders performance indexes
        Schema::table('work_orders', function (Blueprint $table) {
            // KPI and reporting optimized indexes
            $table->index(['factory_id', 'status', 'created_at', 'updated_at'], 'work_orders_kpi_reporting_idx');
            $table->index(['factory_id', 'machine_id', 'status', 'start_time'], 'work_orders_machine_schedule_idx');
            $table->index(['factory_id', 'operator_id', 'status'], 'work_orders_operator_status_idx');

            // Dependency chain optimization
            $table->index(['work_order_group_id', 'dependency_status', 'sequence_order'], 'work_orders_dependency_chain_idx');
            $table->index(['work_order_group_id', 'is_dependency_root', 'status'], 'work_orders_root_status_idx');

            // Time-based queries for dashboards
            $table->index(['factory_id', 'start_time', 'end_time'], 'work_orders_time_range_idx');
            $table->index(['factory_id', 'created_at', 'status'], 'work_orders_created_status_idx');
        });

        // Work Order Logs performance indexes
        Schema::table('work_order_logs', function (Blueprint $table) {
            // KPI calculations and status tracking
            $table->index(['work_order_id', 'changed_at', 'status'], 'work_order_logs_timeline_idx');
            $table->index(['user_id', 'changed_at', 'status'], 'work_order_logs_user_activity_idx');

            // FPY and quality metrics
            $table->index(['work_order_id', 'fpy', 'changed_at'], 'work_order_logs_quality_idx');
            $table->index(['status', 'changed_at', 'ok_qtys', 'scrapped_qtys'], 'work_order_logs_production_idx');
        });

        // Work Order Groups performance indexes
        Schema::table('work_order_groups', function (Blueprint $table) {
            // Factory-based group queries
            $table->index(['factory_id', 'status', 'planned_start_date'], 'work_order_groups_planning_idx');
            $table->index(['factory_id', 'planner_id', 'status'], 'work_order_groups_planner_idx');

            // Timeline and completion tracking
            $table->index(['factory_id', 'actual_start_date', 'actual_completion_date'], 'work_order_groups_timeline_idx');
        });

        // Work Order Dependencies optimization
        Schema::table('work_order_dependencies', function (Blueprint $table) {
            // Dependency resolution queries
            $table->index(['work_order_group_id', 'is_satisfied', 'dependency_type'], 'work_order_dependencies_resolution_idx');
            $table->index(['successor_work_order_id', 'is_satisfied'], 'work_order_dependencies_successor_idx');
        });

        // Work Order Batches performance indexes
        Schema::table('work_order_batches', function (Blueprint $table) {
            // Batch processing and status queries
            $table->index(['work_order_id', 'status', 'batch_number'], 'work_order_batches_processing_idx');
            $table->index(['status', 'started_at', 'completed_at'], 'work_order_batches_timeline_idx');
        });

        // Work Order Batch Keys optimization
        Schema::table('work_order_batch_keys', function (Blueprint $table) {
            // Key consumption and availability
            $table->index(['work_order_id', 'batch_number', 'is_consumed'], 'batch_keys_availability_idx');
            $table->index(['consumed_by_work_order_id', 'consumed_at'], 'batch_keys_consumption_idx');
            $table->index(['is_consumed', 'generated_at', 'work_order_id'], 'batch_keys_status_timeline_idx');
        });

        // Work Order Quantities for reporting
        Schema::table('work_order_quantities', function (Blueprint $table) {
            // Quality and production reporting
            $table->index(['work_order_id', 'created_at', 'ok_quantity', 'scrapped_quantity'], 'work_order_quantities_reporting_idx');
            $table->index(['work_order_log_id', 'reason_id'], 'work_order_quantities_analysis_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('work_orders_kpi_reporting_idx');
            $table->dropIndex('work_orders_machine_schedule_idx');
            $table->dropIndex('work_orders_operator_status_idx');
            $table->dropIndex('work_orders_dependency_chain_idx');
            $table->dropIndex('work_orders_root_status_idx');
            $table->dropIndex('work_orders_time_range_idx');
            $table->dropIndex('work_orders_created_status_idx');
        });

        Schema::table('work_order_logs', function (Blueprint $table) {
            $table->dropIndex('work_order_logs_timeline_idx');
            $table->dropIndex('work_order_logs_user_activity_idx');
            $table->dropIndex('work_order_logs_quality_idx');
            $table->dropIndex('work_order_logs_production_idx');
        });

        Schema::table('work_order_groups', function (Blueprint $table) {
            $table->dropIndex('work_order_groups_planning_idx');
            $table->dropIndex('work_order_groups_planner_idx');
            $table->dropIndex('work_order_groups_timeline_idx');
        });

        Schema::table('work_order_dependencies', function (Blueprint $table) {
            $table->dropIndex('work_order_dependencies_resolution_idx');
            $table->dropIndex('work_order_dependencies_successor_idx');
        });

        Schema::table('work_order_batches', function (Blueprint $table) {
            $table->dropIndex('work_order_batches_processing_idx');
            $table->dropIndex('work_order_batches_timeline_idx');
        });

        Schema::table('work_order_batch_keys', function (Blueprint $table) {
            $table->dropIndex('batch_keys_availability_idx');
            $table->dropIndex('batch_keys_consumption_idx');
            $table->dropIndex('batch_keys_status_timeline_idx');
        });

        Schema::table('work_order_quantities', function (Blueprint $table) {
            $table->dropIndex('work_order_quantities_reporting_idx');
            $table->dropIndex('work_order_quantities_analysis_idx');
        });
    }
};
