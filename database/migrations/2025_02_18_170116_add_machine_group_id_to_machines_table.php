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
        Schema::table('machines', function (Blueprint $table) {
            // Add the foreign key to the machine_groups table
            $table->foreignId('machine_group_id')->nullable()->constrained('machine_groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            // Drop the foreign key if we rollback
            $table->dropForeign(['machine_group_id']);
            $table->dropColumn('machine_group_id');
        });
    }
};
