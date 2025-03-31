<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ok_quantities', function (Blueprint $table) {
            // Ensure the column is added only if it doesn't exist
            if (! Schema::hasColumn('ok_quantities', 'work_order_log_id')) {
                $table->unsignedBigInteger('work_order_log_id')->nullable()->after('work_order_id');
                $table->foreign('work_order_log_id')->references('id')->on('work_order_logs')->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('ok_quantities', function (Blueprint $table) {
            $table->dropForeign(['work_order_log_id']);
            $table->dropColumn('work_order_log_id');
        });
    }
};
