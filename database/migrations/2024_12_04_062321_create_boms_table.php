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
            $table->string('description');
            $table->foreignId('purchase_order_id')->constrained();
            $table-> binary('requirement_pkg')
            ->disk('public') // Specify the disk where files should be stored
            ->directory('uploads/requirements') // Directory to store the files->nullable();
            ->nullable; 
            $table-> binary('process_flowchart')->nullable();
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('operator_proficiency_id')->constrained();
            $table->dateTime('lead_time');
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
