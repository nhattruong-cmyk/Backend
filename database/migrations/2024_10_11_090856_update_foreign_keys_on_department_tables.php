<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('department_user', function (Blueprint $table) {
            $table->dropForeign(['department_id']); // Xóa khóa ngoại cũ
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('restrict'); // Thêm khóa ngoại mới
        });

        Schema::table('project_department', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('restrict');
        });

        Schema::table('task_department', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('department_user', function (Blueprint $table) {
            $table->dropForeign(['department_id']); // Xóa khóa ngoại mới
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade'); // Thêm lại khóa ngoại cũ
        });

        Schema::table('project_department', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
        });

        Schema::table('task_department', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
        });
    }

};
