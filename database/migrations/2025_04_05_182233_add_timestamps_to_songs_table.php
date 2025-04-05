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
        Schema::table('songs', function (Blueprint $table) {
            $table->integer('index_number')->unique()->after('id');
            $table->integer('play_count')->default(0)->after('index_number');
            $table->timestamps(); // created_at, updated_at 컬럼 추가
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['index_number', 'play_count']);
            $table->dropTimestamps(); // created_at, updated_at 컬럼 제거
        });
    }
};
