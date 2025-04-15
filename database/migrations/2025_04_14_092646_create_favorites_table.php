<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'songs' 테이블에 'index_number' 컬럼이 존재하지 않으면 추가
        if (!Schema::hasColumn('songs', 'index_number')) {
            Schema::table('songs', function (Blueprint $table) {
                $table->integer('index_number')->after('id'); // 'id' 뒤에 'index_number' 컬럼 추가
            });
        }
    }

    public function down(): void
    {
        // 'songs' 테이블에서 'index_number' 컬럼을 제거
        if (Schema::hasColumn('songs', 'index_number')) {
            Schema::table('songs', function (Blueprint $table) {
                $table->dropColumn('index_number');
            });
        }
    }
};
