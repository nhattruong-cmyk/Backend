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
            if (!Schema::hasColumn('worktimes', 'worktime_id')) {
                $table->unsignedBigInteger('worktime_id')->nullable()->after('description');
                $table->foreign('worktime_id')->references('id')->on('worktimes')->onDelete('set null');
            }
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worktimes', function (Blueprint $table) {
            $table->dropForeign(['worktime_id']); // Xóa khóa ngoại trước
            $table->dropColumn('worktime_id'); // Sau đó xóa cột
        });
    }
};
