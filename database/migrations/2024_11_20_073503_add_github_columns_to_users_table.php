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
            // Thêm lại cột github_id và github_token với nullable
            $table->string('github_id')->nullable()->after('google_id'); // bạn có thể thay đổi 'email' bằng cột mà bạn muốn
            $table->string('github_token')->nullable()->after('github_id'); // bạn có thể thay đổi 'github_id' bằng cột bạn muốn
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Xóa lại cột github_id và github_token nếu cần rollback
            $table->dropColumn('github_id');
            $table->dropColumn('github_token');
        });
    }
};
