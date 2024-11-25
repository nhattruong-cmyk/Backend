<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // Trong file migration
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('fullname')->nullable()->change();  // Thêm nullable() để cho phép null
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('fullname')->nullable(false)->change();  // Đảm bảo fullname không null nếu rollback
    });
}

};
