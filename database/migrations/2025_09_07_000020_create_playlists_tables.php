<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 128);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index(['user_id']);
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('playlist_id');
            $table->string('item_type', 16); // 'book' or 'chapter'
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['user_id']);
            $table->index(['playlist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
    }
};

