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
            if (!Schema::hasColumn('activity_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        });
        
    }

    public function down()
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Xóa khóa ngoại và cột user_id
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
