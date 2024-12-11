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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();  // Đổi cột id thành UUID và làm khóa chính
            $table->morphs('notifiable');  // Tạo các cột notifiable_id và notifiable_type
            $table->string('type');  // Thêm cột type
            $table->text('data');  // Dữ liệu thông báo (dưới dạng JSON)
            $table->timestamp('read_at')->nullable();  // Thời gian khi thông báo được đánh dấu là đã đọc
            $table->timestamps();  // Thời gian tạo và cập nhật thông báo
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
    
    
};
