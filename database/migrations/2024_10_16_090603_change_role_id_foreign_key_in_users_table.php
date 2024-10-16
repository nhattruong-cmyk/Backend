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
        Schema::table('users', function (Blueprint $table) {
            // Xóa khóa ngoại cũ
            $table->dropForeign(['role_id']);

            // Thêm khóa ngoại mới với onDelete('restrict')
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Khôi phục lại khóa ngoại cũ với onDelete('cascade')
            $table->dropForeign(['role_id']);
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }
};
