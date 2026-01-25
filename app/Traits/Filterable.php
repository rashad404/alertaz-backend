<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Trait for models that support dynamic filtering
 */
trait Filterable
{
    /**
     * Apply a filter array to the query
     */
    public function scopeApplyFilter(Builder $query, ?array $filter): Builder
    {
        if (empty($filter) || empty($filter['conditions'])) {
            return $query;
        }

        $logic = strtoupper($filter['logic'] ?? 'AND');
        $conditions = $filter['conditions'];

        return $query->where(function ($q) use ($conditions, $logic) {
            foreach ($conditions as $index => $condition) {
                $method = $index === 0 ? 'where' : ($logic === 'OR' ? 'orWhere' : 'where');
                $this->applyCondition($q, $condition, $method);
            }
        });
    }

    /**
     * Apply a single condition to the query
     */
    protected function applyCondition(Builder $query, array $condition, string $method): void
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        // Handle computed fields
        if ($field === 'days_until_expiry') {
            // Only apply if model has expiry_at column (e.g., Service, not Customer)
            if (in_array('expiry_at', $this->getDirectColumns())) {
                $this->applyDaysUntilExpiryCondition($query, $operator, $value, $method);
            }
            return;
        }

        // Handle customer.* fields for services
        if (str_starts_with($field, 'customer.')) {
            $customerField = str_replace('customer.', '', $field);
            $query->{$method . 'Has'}('customer', function ($q) use ($customerField, $operator, $value) {
                $this->applySimpleCondition($q, $customerField, $operator, $value, 'where');
            });
            return;
        }

        // Check if field is in the JSON data column
        $isJsonField = $this->isJsonField($field);
        $dbField = $isJsonField ? "data->>{$field}" : $field;

        switch ($operator) {
            case 'equals':
            case 'eq':
            case '=':
                $query->$method($dbField, '=', $value);
                break;

            case 'not_equals':
            case 'neq':
            case '!=':
                $query->$method($dbField, '!=', $value);
                break;

            case 'contains':
            case 'like':
                $query->$method($dbField, 'LIKE', "%{$value}%");
                break;

            case 'not_contains':
                $query->$method($dbField, 'NOT LIKE', "%{$value}%");
                break;

            case 'starts_with':
                $query->$method($dbField, 'LIKE', "{$value}%");
                break;

            case 'ends_with':
                $query->$method($dbField, 'LIKE', "%{$value}");
                break;

            case 'greater_than':
            case 'gt':
            case '>':
                $query->$method($dbField, '>', $value);
                break;

            case 'greater_than_or_equal':
            case 'gte':
            case '>=':
                $query->$method($dbField, '>=', $value);
                break;

            case 'less_than':
            case 'lt':
            case '<':
                $query->$method($dbField, '<', $value);
                break;

            case 'less_than_or_equal':
            case 'lte':
            case '<=':
                $query->$method($dbField, '<=', $value);
                break;

            case 'in':
                $values = is_array($value) ? $value : explode(',', $value);
                $query->{$method . 'In'}($dbField, $values);
                break;

            case 'not_in':
                $values = is_array($value) ? $value : explode(',', $value);
                $query->{$method . 'NotIn'}($dbField, $values);
                break;

            case 'is_null':
            case 'empty':
                $query->{$method . 'Null'}($dbField);
                break;

            case 'is_not_null':
            case 'not_empty':
                $query->{$method . 'NotNull'}($dbField);
                break;

            case 'in_days':
                // Expiry within N days from now
                $date = Carbon::now()->addDays((int) $value)->toDateString();
                $query->$method($dbField, '<=', $date)
                      ->where($dbField, '>=', Carbon::now()->toDateString());
                break;

            case 'in_days_exactly':
                // Expiry exactly N days from now
                $date = Carbon::now()->addDays((int) $value)->toDateString();
                $query->$method($dbField, '=', $date);
                break;

            case 'expired':
                $query->$method($dbField, '<', Carbon::now()->toDateString());
                break;

            case 'not_expired':
                $query->$method($dbField, '>=', Carbon::now()->toDateString());
                break;

            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->{$method . 'Between'}($dbField, $value);
                }
                break;

            case 'date_between':
                if (is_array($value) && count($value) === 2) {
                    $query->$method($dbField, '>=', $value[0])
                          ->where($dbField, '<=', $value[1]);
                }
                break;
        }
    }

    /**
     * Apply condition for days_until_expiry computed field
     */
    protected function applyDaysUntilExpiryCondition(Builder $query, string $operator, $value, string $method): void
    {
        $days = (int) $value;
        $daysExpr = \DB::raw('DATEDIFF(expiry_at, CURDATE())');

        switch ($operator) {
            case 'equals':
            case 'eq':
            case '=':
                $query->$method($daysExpr, '=', $days);
                break;
            case 'not_equals':
            case 'neq':
            case '!=':
                $query->$method($daysExpr, '!=', $days);
                break;
            case 'greater_than':
            case 'gt':
            case '>':
                $query->$method($daysExpr, '>', $days);
                break;
            case 'greater_than_or_equal':
            case 'gte':
            case '>=':
                $query->$method($daysExpr, '>=', $days);
                break;
            case 'less_than':
            case 'lt':
            case '<':
                $query->$method($daysExpr, '<', $days);
                break;
            case 'less_than_or_equal':
            case 'lte':
            case '<=':
                $query->$method($daysExpr, '<=', $days);
                break;
            case 'in_days':
                // Expires within N days (0 to N)
                $query->$method($daysExpr, '>=', 0)
                      ->where($daysExpr, '<=', $days);
                break;
        }
    }

    /**
     * Apply a simple condition (used for nested relations)
     */
    protected function applySimpleCondition(Builder $query, string $field, string $operator, $value, string $method): void
    {
        switch ($operator) {
            case 'equals':
            case 'eq':
            case '=':
                $query->$method($field, '=', $value);
                break;
            case 'not_equals':
            case 'neq':
            case '!=':
                $query->$method($field, '!=', $value);
                break;
            case 'contains':
            case 'like':
                $query->$method($field, 'LIKE', "%{$value}%");
                break;
            case 'is_null':
            case 'empty':
                $query->{$method . 'Null'}($field);
                break;
            case 'is_not_null':
            case 'not_empty':
                $query->{$method . 'NotNull'}($field);
                break;
            default:
                $query->$method($field, '=', $value);
        }
    }

    /**
     * Check if a field is stored in the JSON data column
     * Override this in your model to customize
     */
    protected function isJsonField(string $field): bool
    {
        // By default, check if the field is not in the model's actual columns
        $directColumns = $this->getDirectColumns();
        return !in_array($field, $directColumns);
    }

    /**
     * Get the list of direct (non-JSON) columns
     * Override this in your model to customize
     */
    protected function getDirectColumns(): array
    {
        return ['id', 'client_id', 'name', 'status', 'expiry_at', 'created_at', 'updated_at'];
    }
}
