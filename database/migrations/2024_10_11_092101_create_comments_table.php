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
        if (!Schema::hasTable('comments')) {

            Schema::create('comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('user_id');
                $table->text('comment');
                $table->unsignedBigInteger('parent_id')->nullable(); // Để phản hồi bình luận
                $table->timestamps();

                // Khóa ngoại
                $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade'); // Để quản lý phản hồi

            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
