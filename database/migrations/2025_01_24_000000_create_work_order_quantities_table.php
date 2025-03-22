<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('work_order_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('work_order_log_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('quantity');
            $table->enum('type', ['ok', 'scrapped']);
            $table->foreignId('reason_id')->nullable()->constrained('scrapped_reasons')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('work_order_quantities');
    }
}; 