<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::dropIfExists('assignments');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->text('note')->nullable();
            $table->timestamp('assigned_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('task_id');
           
            $table->timestamps();
        });
    }
};
