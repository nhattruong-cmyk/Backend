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
            // Thay đổi cột github_id và github_token thành nullable
            $table->string('github_id')->nullable()->change();
            $table->string('github_token')->nullable()->change();

            // Xóa cột github_refresh_token
            $table->dropColumn('github_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Đổi lại cột github_id và github_token thành không thể null
            $table->string('github_id')->nullable(false)->change();
            $table->string('github_token')->nullable(false)->change();

            // Thêm lại cột github_refresh_token
            $table->string('github_refresh_token')->nullable();
        });
    }
};
