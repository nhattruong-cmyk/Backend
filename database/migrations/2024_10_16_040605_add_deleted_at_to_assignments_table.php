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
            $table->softDeletes(); // Thêm cột deleted_at
        });
    }
    
    public function down()
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
    
    
    
};
