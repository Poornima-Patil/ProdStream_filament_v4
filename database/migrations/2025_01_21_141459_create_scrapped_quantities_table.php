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
        Schema::create('scrapped_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->foreignId('reason_id')->constrained('scrapped_reasons')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
