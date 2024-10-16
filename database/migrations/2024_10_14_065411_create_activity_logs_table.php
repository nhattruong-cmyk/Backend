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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Cột user_id
            $table->unsignedBigInteger('loggable_id'); // ID của đối tượng được ghi log
            $table->string('loggable_type'); // Loại của đối tượng (model class)
            $table->string('action'); // Hành động (ví dụ: created, updated, deleted)
            $table->json('changes')->nullable(); // Lưu các thay đổi dưới dạng JSON
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
