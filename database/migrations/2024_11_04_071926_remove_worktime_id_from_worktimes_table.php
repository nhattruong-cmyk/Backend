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
        Schema::table('worktimes', function (Blueprint $table) {
            // Hủy bỏ khóa ngoại trước khi xóa cột
            // $table->dropForeign(['worktime_id']);
            $table->dropColumn('worktime_id');
        });
    }

    public function down(): void
    {
        Schema::table('worktimes', function (Blueprint $table) {
            // $table->unsignedBigInteger('worktime_id')->nullable();
            $table->foreign('worktime_id')->references('id')->on('worktimes')->onDelete('set null');
        });
    }
};
