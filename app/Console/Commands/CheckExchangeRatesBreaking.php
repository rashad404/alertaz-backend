<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NewsAIService;
use App\Services\ExchangeRateAnalyzer;
use App\Models\News;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class CheckExchangeRatesBreaking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:check-exchange-breaking
                            {--threshold= : Custom threshold percentage (default from config)}
                            {--hours= : Hours to compare (default: 2)}
                            {--provider= : AI provider to use (claude, openai, gemini)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for significant exchange rate changes and generate breaking news articles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for breaking news in exchange rates...');

        try {
            $analyzer = new ExchangeRateAnalyzer();

            if (!$analyzer->hasRecentData()) {
                $this->warn('No recent exchange rate data available');
                return Command::SUCCESS;
            }

            $threshold = $this->option('threshold') ?? config('ai.thresholds.exchange_rates');
            $hours = $this->option('hours') ?? 2;

            $this->info("Threshold: {$threshold}%");
            $this->info("Comparing with rates from {$hours} hours ago");

            $breakingNews = $analyzer->detectBreakingNews($threshold, $hours);

            if (empty($breakingNews)) {
                $this->info('✓ No breaking news detected. All rates are within threshold.');
                return Command::SUCCESS;
            }

            $this->info('⚠ Breaking news detected for ' . count($breakingNews) . ' currency(ies)');

            $provider = $this->option('provider');
            $aiService = new NewsAIService($provider);
            $category = $this->getOrCreateCategory();

            if (!$category) {
                $this->error('Could not find or create category');
                return Command::FAILURE;
            }

            $createdArticles = 0;

            foreach ($breakingNews as $item) {
                $currency = $item['currency'];
                $data = $item['data'];

                $this->info("\n{$currency}: {$data['change_percent']}% change");
                $this->info("  Current: {$data['current_rate']} AZN");
                $this->info("  Previous: {$data['previous_rate']} AZN");

                if ($this->shouldSkipDuplicate($currency, $data['change_percent'])) {
                    $this->warn("  ⊘ Skipping - similar article already published recently");
                    continue;
                }

                $this->info("  → Generating breaking news article...");

                try {
                    $article = $aiService->generateExchangeRatesBreakingNews($currency, $data);

                    $direction = $data['change_percent'] > 0 ? 'artım' : 'azalma';
                    $hashtags = ['valyuta', 'təcili', strtolower($currency), $direction];

                    $news = News::create([
                        'title' => $article['title'],
                        'body' => $article['content'],
                        'language' => 'az',
                        'category_id' => $category->id,
                        'news_type' => 'official',
                        'status' => false,  // Draft - awaiting admin approval
                        'publish_date' => now(),
                        'hashtags' => $hashtags,
                        'is_ai_generated' => true,
                        'source_url' => 'https://cbar.az/currencies/' . now()->format('d.m.Y') . '.xml',
                    ]);

                    $this->info("  ✓ Article created as DRAFT! ID: {$news->id}");

                    Log::info('Breaking news article created', [
                        'news_id' => $news->id,
                        'currency' => $currency,
                        'change_percent' => $data['change_percent'],
                        'title' => $article['title']
                    ]);

                    $createdArticles++;

                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to generate article: " . $e->getMessage());
                    Log::error('Breaking news generation failed', [
                        'currency' => $currency,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("\n✓ Process completed. Created {$createdArticles} breaking news article(s)");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error checking breaking news: ' . $e->getMessage());
            Log::error('Breaking news check failed', [
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

    /**
     * Check if we should skip creating duplicate article
     * (similar currency and change within last 6 hours)
     */
    protected function shouldSkipDuplicate(string $currency, float $changePercent): bool
    {
        $recentArticle = News::where('language', 'az')
            ->where('created_at', '>=', now()->subHours(6))
            ->whereJsonContains('hashtags', strtolower($currency))
            ->whereJsonContains('hashtags', 'təcili')
            ->first();

        return $recentArticle !== null;
    }
}
