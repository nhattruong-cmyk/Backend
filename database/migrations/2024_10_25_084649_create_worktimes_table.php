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
        Schema::create('worktimes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Cột tên (string)
            $table->date('start_date'); // Cột ngày bắt đầu (date)
            $table->date('end_date')->nullable(); // Cột ngày kết thúc (nullable nếu có thể không có)
            $table->text('description')->nullable(); // Cột mô tả (nullable)
            $table->timestamps(); // Cột created_at và updated_at tự động
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('worktimes');
    }
};
