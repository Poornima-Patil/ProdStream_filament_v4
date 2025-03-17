<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ok_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->softDeletes(); // Soft delete column
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ok_quantities');
    }
};
