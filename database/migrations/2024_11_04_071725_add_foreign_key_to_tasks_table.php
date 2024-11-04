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
        Schema::table('tasks', function (Blueprint $table) {
            // Kiểm tra nếu cột worktime_id chưa tồn tại thì mới thêm
            if (!Schema::hasColumn('tasks', 'worktime_id')) {
                $table->unsignedBigInteger('worktime_id')->nullable()->after('location_tasks');
            }

            // Thêm khóa ngoại cho cột worktime_id
            $table->foreign('worktime_id')
                ->references('id')
                ->on('worktimes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Xóa khóa ngoại và cột worktime_id nếu tồn tại
            if (Schema::hasColumn('tasks', 'worktime_id')) {
                $table->dropForeign(['worktime_id']);
                $table->dropColumn('worktime_id');
            }
        });
    }
};
