<?php

$models = [
    'HomeSliderNews.php',
    'Category.php',
    'Offer.php',
    'RecommendedBank.php',
    'Partner.php',
    'OfferAdvantage.php',
    'OffersDuration.php',
    'OffersCategory.php',
    'HomePageSliderBanner.php',
    'Company.php',
    'AboutPageDynamicData.php',
    'OurMission.php',
    'Credit.php',
    'Faq.php',
    'OfferDuration.php'
];

foreach ($models as $model) {
    $file = __DIR__ . '/app/Models/' . $model;
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Remove the import statement
        $content = str_replace("use Spatie\EloquentSortable\SortableTrait;\n", '', $content);
        
        // Remove SortableTrait from use statements
        $content = str_replace(', SortableTrait', '', $content);
        $content = str_replace('SortableTrait, ', '', $content);
        $content = str_replace('use SortableTrait;', '// use SortableTrait; // Removed - was from Nova package', $content);
        
        // Comment out sortable configuration
        $content = preg_replace(
            '/public \$sortable = \[.*?\];/s',
            '// Removed sortable configuration - was from Nova package',
            $content
        );
        
        file_put_contents($file, $content);
        echo "Fixed: $model\n";
    }
}

echo "All models fixed!\n";