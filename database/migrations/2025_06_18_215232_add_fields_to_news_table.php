<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->json('title')->nullable()->after('id');
            $table->json('body')->nullable()->after('title');
            $table->json('views')->nullable()->after('title');

            $table->string('author')->nullable()->after('views');
            $table->string('hashtags')->nullable()->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['title', 'body', 'author', 'hashtags']);
        });
    }
};
