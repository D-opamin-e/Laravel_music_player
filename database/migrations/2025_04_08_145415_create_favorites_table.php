<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->unsignedBigInteger('song_index'); // 곡 index
            $table->timestamps();

            $table->unique(['ip_address', 'song_index']); // 중복 방지
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
