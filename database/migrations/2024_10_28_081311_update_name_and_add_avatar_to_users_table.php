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
        Schema::table('users', function (Blueprint $table) {
            // Đổi tên cột 'name' thành 'fullname'
            $table->renameColumn('name', 'fullname');

            // Thêm cột 'avatar' để lưu trữ đường dẫn ảnh
            $table->string('avatar')->nullable()->after('fullname'); // Đặt sau cột 'fullname'
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Đổi lại tên cột 'fullname' về 'name'
            $table->renameColumn('fullname', 'name');

            // Xóa cột 'avatar'
            $table->dropColumn('avatar');
        });
    }
};
