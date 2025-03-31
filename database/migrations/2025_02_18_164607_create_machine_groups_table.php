<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMachineGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('machine_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_name'); // Column for group name
            $table->text('description'); // Column for description
            $table->softDeletes(); // Column for soft deletes
            $table->timestamps();
            $table->foreignId('factory_id')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('machine_groups');
    }
}
