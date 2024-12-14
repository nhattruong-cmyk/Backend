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
            $table->boolean('is_duplicate')->default(false); // Cột đánh dấu bản sao
        });
    }
    
    public function down()
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('is_duplicate');
        });
    }
    
};
