<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NewsAIService;
use App\Services\ExchangeRateAnalyzer;
use App\Models\News;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class GenerateExchangeRatesSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:generate-exchange-summary {--provider= : AI provider to use (claude, openai, gemini)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily summary article about exchange rates using AI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting exchange rates summary generation...');

        try {
            $analyzer = new ExchangeRateAnalyzer();

            if (!$analyzer->hasRecentData()) {
                $this->error('No recent exchange rate data available. Please run: php artisan exchange-rates:fetch');
                return Command::FAILURE;
            }

            $this->info('Fetching exchange rate data...');
            $ratesData = $analyzer->getDataForDailySummary();

            if (empty($ratesData['current'])) {
                $this->error('No current exchange rates found');
                return Command::FAILURE;
            }

            $this->info('Current rates: ' . json_encode($ratesData['current']));
            $this->info('Previous rates: ' . json_encode($ratesData['previous']));

            $provider = $this->option('provider');
            $aiService = new NewsAIService($provider);

            $this->info('Generating article with AI...');
            if ($provider) {
                $this->info("Using provider: {$provider}");
            }

            $article = $aiService->generateExchangeRatesSummary($ratesData);

            $this->info('Article generated successfully!');
            $this->info('Title: ' . $article['title']);
            $this->info('Content length: ' . str_word_count($article['content']) . ' words');

            $category = $this->getOrCreateCategory();

            if (!$category) {
                $this->error('Could not find or create Valyuta category');
                return Command::FAILURE;
            }

            $this->info('Saving article to database...');

            $news = News::create([
                'title' => $article['title'],
                'body' => $article['content'],
                'language' => 'az',
                'category_id' => $category->id,
                'news_type' => 'official',
                'status' => false,  // Draft - awaiting admin approval
                'publish_date' => now(),
                'hashtags' => ['valyuta', 'məzənnə', 'günlük-icmal'],
                'is_ai_generated' => true,
                'source_url' => 'https://cbar.az/currencies/' . now()->format('d.m.Y') . '.xml',
            ]);

            $this->info("✓ Article created as DRAFT! ID: {$news->id}");
            $this->info("  Status: Awaiting admin approval");
            $this->info("  URL: /news/{$news->slug}");

            Log::info('Exchange rates summary generated', [
                'news_id' => $news->id,
                'title' => $article['title'],
                'category' => $category->title,
                'provider' => $provider ?? config('ai.default_provider')
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error generating summary: ' . $e->getMessage());
            Log::error('Exchange rates summary generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Get or create the Valyuta/Finance category
     */
    protected function getOrCreateCategory()
    {
        $category = Category::whereRaw("JSON_EXTRACT(title, '$.az') LIKE ?", ['%Valyuta%'])
            ->orWhereRaw("JSON_EXTRACT(title, '$.az') LIKE ?", ['%Maliyyə%'])
            ->first();

        if (!$category) {
            $this->warn('Creating new Valyuta category...');

            $category = Category::create([
                'title' => [
                    'az' => 'Valyuta məzənnələri',
                    'en' => 'Exchange Rates',
                    'ru' => 'Валютные курсы'
                ],
                'slug' => 'valyuta-mezenneleri',
                'status' => true,
                'order' => 999,
            ]);
        }

        return $category;
    }
}
