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
        Schema::create('work_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade'); // Link to work orders table
            $table->string('status'); // Status of the work order at the time of log
            $table->timestamp('changed_at')->useCurrent(); // Timestamp for when the status was changed
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User who made the change
            $table->integer('ok_qtys')->default(0);
            $table->integer('scrapped_qtys')->default(0);
            $table->integer('remaining')->nullable();
            $table->foreignId('scrapped_reason_id')->nullable()->constrained();
            $table->foreignId('hold_reason_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_logs');
    }
};
