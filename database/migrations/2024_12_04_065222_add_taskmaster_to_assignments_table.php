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
        // Thêm cột 'taskmaster' vào bảng assignments
        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('taskmaster')->nullable(); // Thêm cột taskmaster, kiểu unsignedBigInteger
            
            // Thiết lập khóa ngoại với bảng users
            $table->foreign('taskmaster')->references('id')->on('users')->onDelete('set null');
        });
    }
    
    public function down()
    {
        // Xóa cột 'taskmaster' và khóa ngoại nếu rollback migration
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['taskmaster']);
            $table->dropColumn('taskmaster');
        });
    }
    
};
