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
    Schema::table('worktimes', function (Blueprint $table) {
        $table->tinyInteger('status')->nullable(); // Thêm cột status kiểu tinyint, có thể null
    });
}

public function down()
{
    Schema::table('worktimes', function (Blueprint $table) {
        $table->dropColumn('status'); // Xóa cột status
    });
}

};
