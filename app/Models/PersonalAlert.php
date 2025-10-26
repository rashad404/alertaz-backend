<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'alert_type_id',
        'name',
        'asset',
        'conditions',
        'notification_channels',
        'check_frequency',
        'is_active',
        'is_recurring',
        'last_triggered_at',
        'last_checked_at',
        'trigger_count',
        'metadata',
    ];

    protected $casts = [
        'conditions' => 'array',
        'notification_channels' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'last_triggered_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    protected $appends = ['service_type'];

    /**
     * Get the service type from alert type relationship.
     */
    public function getServiceTypeAttribute()
    {
        return $this->alertType?->slug;
    }

    /**
     * Get the user that owns the alert.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the alert type.
     */
    public function alertType()
    {
        return $this->belongsTo(AlertType::class);
    }

    /**
     * Get the alert history.
     */
    public function history()
    {
        return $this->hasMany(AlertHistory::class);
    }

    /**
     * Check if the alert conditions are met.
     */
    public function checkConditions($currentValue)
    {
        $conditions = $this->conditions;

        // Simple condition check (can be expanded)
        if (!isset($conditions['field']) || !isset($conditions['operator']) || !isset($conditions['value'])) {
            return false;
        }

        $field = $conditions['field'];
        $operator = $conditions['operator'];
        $targetValue = $conditions['value'];

        // If current value is an array/object, extract the field
        if (is_array($currentValue) || is_object($currentValue)) {
            $currentValue = data_get($currentValue, $field);
        }

        switch ($operator) {
            case '=':
            case '==':
            case 'equals':
                return $currentValue == $targetValue;
            case '>':
            case 'greater':
                return $currentValue > $targetValue;
            case '>=':
            case 'greater_equal':
                return $currentValue >= $targetValue;
            case '<':
            case 'less':
                return $currentValue < $targetValue;
            case '<=':
            case 'less_equal':
                return $currentValue <= $targetValue;
            case '!=':
            case 'not_equals':
                return $currentValue != $targetValue;
            default:
                return false;
        }
    }

    /**
     * Scope for active alerts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for alerts that need checking.
     */
    public function scopeNeedsChecking($query)
    {
        $now = now();
        return $query->active()
            ->where(function ($q) use ($now) {
                $q->whereNull('last_checked_at')
                    ->orWhereRaw('DATE_ADD(last_checked_at, INTERVAL check_frequency SECOND) <= ?', [$now]);
            });
    }
}