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
        Schema::table('work_order_groups', function (Blueprint $table) {
            $table->json('batch_configuration')->nullable()->after('metadata')->comment('Batch size configuration for each work order in the group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_groups', function (Blueprint $table) {
            $table->dropColumn('batch_configuration');
        });
    }
};
