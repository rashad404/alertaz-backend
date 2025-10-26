<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertParseCache extends Model
{
    protected $table = 'alert_parse_cache';

    protected $fillable = [
        'input_text',
        'normalized_pattern',
        'extracted_variables',
        'parsed_result',
        'confidence',
        'usage_count',
        'ai_provider',
    ];

    protected $casts = [
        'extracted_variables' => 'array',
        'parsed_result' => 'array',
        'confidence' => 'float',
        'usage_count' => 'integer',
    ];

    /**
     * Increment usage count when cache is hit
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Find cached result by similar pattern
     */
    public static function findByPattern(string $pattern)
    {
        return static::where('normalized_pattern', $pattern)
            ->orderBy('usage_count', 'desc')
            ->first();
    }
}
