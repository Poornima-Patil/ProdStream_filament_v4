<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Check and add indexes only if they don't exist
            if (!$this->indexExists('work_orders', 'work_orders_factory_status_idx')) {
                $table->index(['factory_id', 'status'], 'work_orders_factory_status_idx');
            }
            if (!$this->indexExists('work_orders', 'work_orders_factory_created_idx')) {
                $table->index(['factory_id', 'created_at'], 'work_orders_factory_created_idx');
            }
            if (!$this->indexExists('work_orders', 'work_orders_factory_updated_idx')) {
                $table->index(['factory_id', 'updated_at'], 'work_orders_factory_updated_idx');
            }
            if (!$this->indexExists('work_orders', 'work_orders_kpi_completion_idx')) {
                $table->index(['factory_id', 'status', 'updated_at'], 'work_orders_kpi_completion_idx');
            }
        });

        Schema::table('work_order_logs', function (Blueprint $table) {
            if (!$this->indexExists('work_order_logs', 'work_order_logs_status_time_idx')) {
                $table->index(['work_order_id', 'status', 'created_at'], 'work_order_logs_status_time_idx');
            }
            if (!$this->indexExists('work_order_logs', 'work_order_logs_time_status_idx')) {
                $table->index(['created_at', 'status'], 'work_order_logs_time_status_idx');
            }
        });

        Schema::table('boms', function (Blueprint $table) {
            if (!$this->indexExists('boms', 'boms_factory_status_idx')) {
                $table->index(['factory_id', 'status'], 'boms_factory_status_idx');
            }
            if (!$this->indexExists('boms', 'boms_factory_created_idx')) {
                $table->index(['factory_id', 'created_at'], 'boms_factory_created_idx');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!$this->indexExists('purchase_orders', 'purchase_orders_factory_created_idx')) {
                $table->index(['factory_id', 'created_at'], 'purchase_orders_factory_created_idx');
            }
        });

        Schema::table('machines', function (Blueprint $table) {
            if (!$this->indexExists('machines', 'machines_factory_status_idx')) {
                $table->index(['factory_id', 'status'], 'machines_factory_status_idx');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $index) {
            if ($index->Key_name === $indexName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if ($this->indexExists('work_orders', 'work_orders_factory_status_idx')) {
                $table->dropIndex('work_orders_factory_status_idx');
            }
            if ($this->indexExists('work_orders', 'work_orders_factory_created_idx')) {
                $table->dropIndex('work_orders_factory_created_idx');
            }
            if ($this->indexExists('work_orders', 'work_orders_factory_updated_idx')) {
                $table->dropIndex('work_orders_factory_updated_idx');
            }
            if ($this->indexExists('work_orders', 'work_orders_kpi_completion_idx')) {
                $table->dropIndex('work_orders_kpi_completion_idx');
            }
        });

        Schema::table('work_order_logs', function (Blueprint $table) {
            if ($this->indexExists('work_order_logs', 'work_order_logs_status_time_idx')) {
                $table->dropIndex('work_order_logs_status_time_idx');
            }
            if ($this->indexExists('work_order_logs', 'work_order_logs_time_status_idx')) {
                $table->dropIndex('work_order_logs_time_status_idx');
            }
        });

        Schema::table('boms', function (Blueprint $table) {
            if ($this->indexExists('boms', 'boms_factory_status_idx')) {
                $table->dropIndex('boms_factory_status_idx');
            }
            if ($this->indexExists('boms', 'boms_factory_created_idx')) {
                $table->dropIndex('boms_factory_created_idx');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if ($this->indexExists('purchase_orders', 'purchase_orders_factory_created_idx')) {
                $table->dropIndex('purchase_orders_factory_created_idx');
            }
        });

        Schema::table('machines', function (Blueprint $table) {
            if ($this->indexExists('machines', 'machines_factory_status_idx')) {
                $table->dropIndex('machines_factory_status_idx');
            }
        });
    }
};
