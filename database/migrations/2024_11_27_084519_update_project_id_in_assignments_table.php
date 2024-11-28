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
        Schema::table('assignments', function (Blueprint $table) {
            // Đảm bảo project_id không được để null
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Cho phép project_id được null (rollback)
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
    }
};
