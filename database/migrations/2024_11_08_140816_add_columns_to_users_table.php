<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_code')->nullable();
            $table->string('delete_token')->nullable()->unique();  // Thêm cột delete_token
            $table->string('phone_number')->nullable(); // Cho phép phone_number là null
            $table->timestamp('verification_code_expires_at')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('otp_code')->nullable(); // Thêm cột otp_code
            $table->boolean('is_verified_for_reset')->default(false);  // Trạng thái xác minh để đổi mật khẩu

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verification_code');
            $table->dropColumn('delete_token');
            $table->dropColumn('phone_number');
            $table->dropColumn('verification_code_expires_at');
            $table->dropColumn('otp_expires_at');
            $table->dropColumn('otp_code');
            $table->dropColumn('is_verified_for_reset');
        });
    }
};
