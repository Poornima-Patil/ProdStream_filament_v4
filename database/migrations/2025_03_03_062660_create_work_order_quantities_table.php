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
            $table->foreignId('work_order_log_id')->constrained()->onDelete('cascade');
            $table->integer('ok_quantity')->default(0);
            $table->integer('scrapped_quantity')->default(0);
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
