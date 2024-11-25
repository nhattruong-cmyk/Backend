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
            // Xóa cột github_id và github_token
            $table->dropColumn('github_id');
            $table->dropColumn('github_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm lại cột github_id và github_token
            $table->string('github_id')->nullable();
            $table->string('github_token')->nullable();
        });
    }
};
