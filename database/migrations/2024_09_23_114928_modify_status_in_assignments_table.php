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
        Schema::table('assignments', function (Blueprint $table) {
            // Đảm bảo rằng không có dữ liệu nào bị mất khi chuyển đổi
            // Bạn có thể cần phải cập nhật dữ liệu hiện tại để phù hợp với kiểu mới
            // Ví dụ:
            // DB::table('assignments')->where('status', 'pending')->update(['status' => 0]);
            // DB::table('assignments')->where('status', 'in progress')->update(['status' => 1]);
            // DB::table('assignments')->where('status', 'completed')->update(['status' => 2]);

            // Thay đổi kiểu dữ liệu của cột 'status' từ ENUM thành INTEGER
            $table->integer('status')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Đảm bảo rằng bạn có thể chuyển đổi ngược lại nếu cần thiết
            // Chuyển đổi từ INTEGER trở lại ENUM
            $table->enum('status', ['pending', 'in progress', 'completed'])->default('pending')->change();
        });
    }
};
