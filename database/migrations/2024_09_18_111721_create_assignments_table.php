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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->text('note')->nullable();
            $table->date('assigned_date'); // Ngày phân công
            $table->timestamps(); // Tạo các trường created_at và updated_at
            $table->unsignedBigInteger('role_id'); // Khóa ngoại
            $table->unsignedBigInteger('user_id'); // Khóa ngoại
            $table->unsignedBigInteger('task_id'); // Khóa ngoại


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
