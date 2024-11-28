<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Xóa khóa ngoại trước
            $table->dropForeign(['project_id']);
            // Xóa cột project_id
            $table->dropColumn('project_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Thêm lại cột project_id
            $table->unsignedBigInteger('project_id')->nullable();

            // Thêm lại khóa ngoại
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }
};
