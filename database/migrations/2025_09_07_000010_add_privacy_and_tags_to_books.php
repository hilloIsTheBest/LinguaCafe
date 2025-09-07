<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (!Schema::hasColumn('books', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('language');
            }
            if (!Schema::hasColumn('books', 'tags')) {
                $table->text('tags')->nullable()->after('is_public'); // JSON-encoded array of strings
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'tags')) {
                $table->dropColumn('tags');
            }
            if (Schema::hasColumn('books', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });
    }
};

