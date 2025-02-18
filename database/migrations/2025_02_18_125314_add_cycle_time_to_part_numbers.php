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
        Schema::table('part_numbers', function (Blueprint $table) {
            $table->unsignedInteger('cycle_time')->after('description')->default(0); // Default 0 seconds
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_numbers', function (Blueprint $table) {
            $table->dropColumn('cycle_time');
        });
    }
};
