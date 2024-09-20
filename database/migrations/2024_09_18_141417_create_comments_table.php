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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            
            // Thêm cột khóa ngoại task_id và user_id
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            
            // Thêm cột content để lưu nội dung bình luận
            $table->text('content');

            // Timestamps (created_at, updated_at)
            $table->timestamps();

            // Định nghĩa khóa ngoại cho task_id và user_id
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Xóa khóa ngoại trước khi xóa bảng
            $table->dropForeign(['task_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::dropIfExists('comments');
    }
};
