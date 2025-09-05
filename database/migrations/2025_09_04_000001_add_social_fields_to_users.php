<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('coins')->default(0);
            $table->integer('streak_count')->default(0);
            $table->integer('best_streak')->default(0);
            $table->date('last_login_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['coins', 'streak_count', 'best_streak', 'last_login_date']);
        });
    }
};

