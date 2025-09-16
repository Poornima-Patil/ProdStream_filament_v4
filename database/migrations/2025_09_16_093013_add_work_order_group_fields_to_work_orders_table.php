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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('work_order_group_id')->nullable()->constrained('work_order_groups')->onDelete('set null');
            $table->enum('dependency_status', ['unassigned', 'ready', 'assigned', 'blocked'])->default('assigned');
            $table->integer('sequence_order')->nullable(); // Order within the group (1, 2, 3...)
            $table->boolean('is_dependency_root')->default(false); // First WO in dependency chain
            $table->datetime('dependency_satisfied_at')->nullable(); // When dependencies were satisfied
            $table->json('dependency_metadata')->nullable(); // Additional dependency-related data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['work_order_group_id']);
            $table->dropColumn([
                'work_order_group_id',
                'dependency_status',
                'sequence_order',
                'is_dependency_root',
                'dependency_satisfied_at',
                'dependency_metadata'
            ]);
        });
    }
};
