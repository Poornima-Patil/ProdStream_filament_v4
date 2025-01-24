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
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Drop the existing cust_id string column
            if (Schema::hasColumn('purchase_orders', 'cust_id')) {
                $table->dropColumn('cust_id');
            }

            // Add cust_id as an unsignedBigInteger and set it as a foreign key
            $table->unsignedBigInteger('cust_id')->nullable()->after('price');
            $table->foreign('cust_id')->references('id')->on('customer_information')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Drop the foreign key and column
            $table->dropForeign(['cust_id']);
            $table->dropColumn('cust_id');

            // Optionally, revert back to a string column
            $table->string('cust_id')->nullable()->after('price');
        });
    }
};
