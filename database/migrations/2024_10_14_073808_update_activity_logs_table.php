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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Thêm các cột nếu chúng chưa tồn tại
            if (!Schema::hasColumn('activity_logs', 'loggable_id')) {
                $table->unsignedBigInteger('loggable_id');
            }
            if (!Schema::hasColumn('activity_logs', 'loggable_type')) {
                $table->string('loggable_type');
            }
            if (!Schema::hasColumn('activity_logs', 'action')) {
                $table->string('action');
            }
            if (!Schema::hasColumn('activity_logs', 'changes')) {
                $table->json('changes')->nullable();
            }
            // Kiểm tra nếu cột `created_at` chưa tồn tại
            if (!Schema::hasColumn('activity_logs', 'created_at')) {
                $table->timestamps(); // Thêm cả created_at và updated_at
            }
        });
    }

    public function down()
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Xóa các cột nếu cần
            $table->dropColumn(['loggable_id', 'loggable_type', 'action', 'changes']);
        });
    }
};
