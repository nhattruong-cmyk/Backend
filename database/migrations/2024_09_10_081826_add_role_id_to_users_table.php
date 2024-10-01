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

        if (!Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->default(3); // Giả định role_id = 2 là role mặc định
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
         
            });
        }


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
