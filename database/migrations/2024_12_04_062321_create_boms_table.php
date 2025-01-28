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
        Schema::create('boms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained();
         
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('operator_proficiency_id')->constrained();
            $table->dateTime('lead_time')->nullable();
            $table->boolean('status');
            $table->timestamps();
            $table->foreignId('factory_id')->constrained();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};
