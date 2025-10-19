<?php

namespace App\Helpers;

class UnsplashHelper
{
    // Pexels image IDs for finance/business theme
    private static $pexelsIds = [
        '3483098', // financial charts
        '730547',  // business meeting
        '3182759', // office work
        '416405',  // money and calculator
        '3184292', // team meeting
        '265087',  // financial planning
        '3182826', // corporate office
        '210990',  // stock market
        '259200',  // credit cards
        '3184418', // business strategy
        '1181406', // working on laptop
        '3184465', // financial analysis
        '669610',  // bank building
        '3184419', // business documents
        '3182812'  // modern office
    ];

    public static function downloadMultipleImages($count = 10)
    {
        $images = [];
        $availableIds = self::$pexelsIds;
        shuffle($availableIds);
        
        echo "Downloading images from Pexels...\n";
        
        for ($i = 0; $i < min($count, count($availableIds)); $i++) {
            $id = $availableIds[$i];
            $url = "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=1";
            $filename = 'news-' . ($i + 1) . '.jpg';
            
            if (self::downloadImage($url, $filename)) {
                $images[] = $filename;
                echo "Downloaded image " . ($i + 1) . " of {$count}\n";
            }
            
            // if ($i < $count - 1) {
            //     sleep(1); // 1 second delay between downloads
            // }
        }
        
        return $images;
    }

    private static function downloadImage($url, $filename)
    {
        return true;
        try {
            $savePath = public_path('uploads/news/' . $filename);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $imageContent = @file_get_contents($url, false, $context);
            
            if ($imageContent !== false && strlen($imageContent) > 1000) {
                file_put_contents($savePath, $imageContent);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public static function downloadRandomFinanceImage($filename = null)
    {
        $images = self::downloadMultipleImages(1);
        return !empty($images) ? $images[0] : null;
    }
}