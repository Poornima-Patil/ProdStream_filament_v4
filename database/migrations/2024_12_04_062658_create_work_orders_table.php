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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained();
            $table->integer('qty');
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('operator_id')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->time('delay_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('status')->nullable();
            $table->integer('ok_qtys')->default(0);
            $table->integer('scrapped_qtys')->default(0);
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
        Schema::dropIfExists('work_orders');
    }
};
