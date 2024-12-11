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
        // Xóa khóa ngoại của bảng notifications với bảng users
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);  // Xóa khóa ngoại liên kết với user_id
        });

        // Xóa bảng notifications
        Schema::dropIfExists('notifications');
    }

    public function down()
    {
        // Nếu cần phục hồi bảng notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->timestamps();

            // Tạo lại khóa ngoại
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
