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
        Schema::table('work_order_logs', function (Blueprint $table) {
            $table->float('fpy')->default(0)->after('hold_reason_id')->comment('First Pass Yield percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_logs', function (Blueprint $table) {
            $table->dropColumn('fpy');
        });
    }
};

