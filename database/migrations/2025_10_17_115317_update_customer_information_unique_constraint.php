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
        Schema::table('customer_information', function (Blueprint $table) {
            // Drop the existing unique constraint on customer_id
            $table->dropUnique(['customer_id']);

            // Add composite unique constraint on customer_id and factory_id
            $table->unique(['customer_id', 'factory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_information', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique(['customer_id', 'factory_id']);

            // Restore the original unique constraint on customer_id
            $table->unique('customer_id');
        });
    }
};
