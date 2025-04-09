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
        Schema::table('songs', function (Blueprint $table) {
            $table->string('cover_filename')->nullable(); // 새 컬럼 추가
        });
    }
    
    public function down()
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn('cover_filename'); // 롤백 시 삭제
        });
    }
    
};
