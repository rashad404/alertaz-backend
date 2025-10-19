<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Add language field
            $table->string('language', 2)->default('az')->after('id');
            
            // Add new non-JSON columns
            $table->text('title_single')->nullable()->after('language');
            $table->longText('body_single')->nullable()->after('title_single');
            $table->text('seo_title_single')->nullable()->after('body_single');
            $table->text('seo_keywords_single')->nullable()->after('seo_title_single');
            $table->longText('seo_description_single')->nullable()->after('seo_keywords_single');
            $table->integer('views_single')->default(0)->after('seo_description_single');
        });
        
        // Migrate existing data - we'll take the 'az' version as default
        $newsItems = DB::table('news')->get();
        foreach ($newsItems as $news) {
            $title = json_decode($news->title, true);
            $body = json_decode($news->body, true);
            $seoTitle = json_decode($news->seo_title, true);
            $seoKeywords = json_decode($news->seo_keywords, true);
            $seoDescription = json_decode($news->seo_description, true);
            $views = json_decode($news->views, true);
            
            DB::table('news')->where('id', $news->id)->update([
                'title_single' => is_array($title) ? ($title['az'] ?? $title['en'] ?? $title['ru'] ?? '') : $news->title,
                'body_single' => is_array($body) ? ($body['az'] ?? $body['en'] ?? $body['ru'] ?? '') : $news->body,
                'seo_title_single' => is_array($seoTitle) ? ($seoTitle['az'] ?? $seoTitle['en'] ?? $seoTitle['ru'] ?? '') : $news->seo_title,
                'seo_keywords_single' => is_array($seoKeywords) ? ($seoKeywords['az'] ?? $seoKeywords['en'] ?? $seoKeywords['ru'] ?? '') : $news->seo_keywords,
                'seo_description_single' => is_array($seoDescription) ? ($seoDescription['az'] ?? $seoDescription['en'] ?? $seoDescription['ru'] ?? '') : $news->seo_description,
                'views_single' => is_array($views) ? ($views['az'] ?? array_sum($views) ?? 0) : (int)$news->views,
            ]);
        }
        
        // Drop old JSON columns
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['title', 'body', 'seo_title', 'seo_keywords', 'seo_description', 'views']);
        });
        
        // Rename new columns to original names
        Schema::table('news', function (Blueprint $table) {
            $table->renameColumn('title_single', 'title');
            $table->renameColumn('body_single', 'body');
            $table->renameColumn('seo_title_single', 'seo_title');
            $table->renameColumn('seo_keywords_single', 'seo_keywords');
            $table->renameColumn('seo_description_single', 'seo_description');
            $table->renameColumn('views_single', 'views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Add back JSON columns
            $table->json('title_json')->nullable()->after('language');
            $table->json('body_json')->nullable()->after('title_json');
            $table->json('seo_title_json')->nullable()->after('body_json');
            $table->json('seo_keywords_json')->nullable()->after('seo_title_json');
            $table->json('seo_description_json')->nullable()->after('seo_keywords_json');
            $table->json('views_json')->nullable()->after('seo_description_json');
        });
        
        // Migrate data back to JSON format
        $newsItems = DB::table('news')->get();
        foreach ($newsItems as $news) {
            $lang = $news->language ?? 'az';
            DB::table('news')->where('id', $news->id)->update([
                'title_json' => json_encode([$lang => $news->title]),
                'body_json' => json_encode([$lang => $news->body]),
                'seo_title_json' => json_encode([$lang => $news->seo_title]),
                'seo_keywords_json' => json_encode([$lang => $news->seo_keywords]),
                'seo_description_json' => json_encode([$lang => $news->seo_description]),
                'views_json' => json_encode([$lang => $news->views]),
            ]);
        }
        
        // Drop single columns
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['title', 'body', 'seo_title', 'seo_keywords', 'seo_description', 'views', 'language']);
        });
        
        // Rename JSON columns back
        Schema::table('news', function (Blueprint $table) {
            $table->renameColumn('title_json', 'title');
            $table->renameColumn('body_json', 'body');
            $table->renameColumn('seo_title_json', 'seo_title');
            $table->renameColumn('seo_keywords_json', 'seo_keywords');
            $table->renameColumn('seo_description_json', 'seo_description');
            $table->renameColumn('views_json', 'views');
        });
    }
};
