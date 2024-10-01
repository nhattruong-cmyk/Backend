<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('project_name');
                $table->text('description')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedTinyInteger('status')->default(1);
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users');

            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
