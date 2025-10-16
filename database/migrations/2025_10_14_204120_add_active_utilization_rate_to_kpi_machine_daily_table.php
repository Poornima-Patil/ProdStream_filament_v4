<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_machine_daily', function (Blueprint $table) {
            $table->decimal('active_utilization_rate', 5, 2)->nullable()->after('utilization_rate');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_machine_daily', function (Blueprint $table) {
            $table->dropColumn('active_utilization_rate');
        });
    }
};
