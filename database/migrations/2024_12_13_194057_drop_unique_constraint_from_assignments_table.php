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
        // Xóa ràng buộc duy nhất (unique constraint) trên các cột
        Schema::table('assignments', function (Blueprint $table) {
            // Xóa chỉ mục duy nhất đã được tạo trước đó, thay 'assignments_task_id_user_id_department_id_unique' bằng tên ràng buộc cụ thể
            $table->dropUnique('assignments_task_id_user_id_department_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Nếu muốn khôi phục lại ràng buộc duy nhất trong tương lai, có thể thêm lại chỉ mục unique
        Schema::table('assignments', function (Blueprint $table) {
            $table->unique(['task_id', 'user_id', 'department_id']);
        });
    }
};
