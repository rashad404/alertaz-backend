<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SegmentQueryBuilder
{
    /**
     * Special computed fields that don't exist in JSON data
     */
    protected array $computedFields = ['days_until_expiry'];

    /**
     * Direct columns (not in JSON) for each target type
     */
    protected array $directColumns = [
        'customer' => ['id', 'external_id', 'phone', 'email', 'name', 'created_at', 'updated_at'],
        'service' => ['id', 'external_id', 'name', 'expiry_at', 'status', 'created_at', 'updated_at'],
    ];

    /**
     * Apply segment filters to query
     */
    public function applyFilters(Builder $query, array $filter, string $targetType = 'customer'): Builder
    {
        if (empty($filter['conditions'])) {
            return $query;
        }

        $logic = $filter['logic'] ?? 'AND';
        $conditions = $filter['conditions'];

        if ($logic === 'AND') {
            foreach ($conditions as $condition) {
                $this->applyCondition($query, $condition, 'and', $targetType);
            }
        } else {
            // OR logic
            $query->where(function ($q) use ($conditions, $targetType) {
                foreach ($conditions as $condition) {
                    $this->applyCondition($q, $condition, 'or', $targetType);
                }
            });
        }

        return $query;
    }

    /**
     * Apply single condition to query
     */
    protected function applyCondition(Builder $query, array $condition, string $boolean = 'and', string $targetType = 'customer'): void
    {
        $field = $condition['field'] ?? null;
        if ($field === null) {
            return;
        }
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        // Handle computed fields specially
        if (in_array($field, $this->computedFields)) {
            $this->applyComputedFieldCondition($query, $field, $operator, $value, $boolean, $targetType);
            return;
        }

        // Check if this is a direct column
        $directCols = $this->directColumns[$targetType] ?? [];
        if (in_array($field, $directCols)) {
            $this->applyDirectColumnCondition($query, $field, $operator, $value, $boolean);
            return;
        }

        // JSON field in 'data' column
        $jsonPath = "data->{$field}";

        if ($boolean === 'or') {
            $query->where(function ($subQuery) use ($field, $operator, $value, $jsonPath) {
                $this->applyJsonCondition($subQuery, $field, $operator, $value, $jsonPath, 'and');
            }, null, null, 'or');
            return;
        }

        $this->applyJsonCondition($query, $field, $operator, $value, $jsonPath, $boolean);
    }

    /**
     * Apply condition for computed fields like days_until_expiry
     */
    protected function applyComputedFieldCondition(Builder $query, string $field, string $operator, $value, string $boolean, string $targetType): void
    {
        if ($field === 'days_until_expiry' && $targetType === 'service') {
            // days_until_expiry = DATEDIFF(expiry_at, CURDATE())
            $daysExpr = 'DATEDIFF(`expiry_at`, CURDATE())';

            // Ensure expiry_at is not null
            $query->whereNotNull('expiry_at', $boolean);

            $numericValue = is_numeric($value) ? (int)$value : null;
            if ($numericValue === null) {
                return;
            }

            match ($operator) {
                'equals' => $query->whereRaw("{$daysExpr} = ?", [$numericValue], $boolean),
                'not_equals' => $query->whereRaw("{$daysExpr} != ?", [$numericValue], $boolean),
                'greater_than' => $query->whereRaw("{$daysExpr} > ?", [$numericValue], $boolean),
                'less_than' => $query->whereRaw("{$daysExpr} < ?", [$numericValue], $boolean),
                'greater_than_or_equal' => $query->whereRaw("{$daysExpr} >= ?", [$numericValue], $boolean),
                'less_than_or_equal' => $query->whereRaw("{$daysExpr} <= ?", [$numericValue], $boolean),
                default => null
            };
        }
    }

    /**
     * Apply condition for direct database columns
     */
    protected function applyDirectColumnCondition(Builder $query, string $field, string $operator, $value, string $boolean): void
    {
        match ($operator) {
            'equals' => $query->where($field, '=', $value, $boolean),
            'not_equals' => $query->where($field, '!=', $value, $boolean),
            'contains' => $query->where($field, 'LIKE', "%{$value}%", $boolean),
            'not_contains' => $query->where($field, 'NOT LIKE', "%{$value}%", $boolean),
            'starts_with' => $query->where($field, 'LIKE', "{$value}%", $boolean),
            'ends_with' => $query->where($field, 'LIKE', "%{$value}", $boolean),
            'is_set' => $query->whereNotNull($field, $boolean),
            'is_not_set' => $query->whereNull($field, $boolean),
            'greater_than' => $query->where($field, '>', $value, $boolean),
            'less_than' => $query->where($field, '<', $value, $boolean),
            'greater_than_or_equal' => $query->where($field, '>=', $value, $boolean),
            'less_than_or_equal' => $query->where($field, '<=', $value, $boolean),
            'in' => $query->whereIn($field, (array)$value, $boolean),
            'not_in' => $query->whereNotIn($field, (array)$value, $boolean),
            default => null
        };
    }

    /**
     * Apply condition for JSON data column
     */
    protected function applyJsonCondition(Builder $query, string $field, string $operator, $value, string $jsonPath, string $boolean): void
    {
        // For most operators, ensure the field exists
        $operatorsWithoutExistCheck = ['is_not_set', 'is_empty'];
        if (!in_array($operator, $operatorsWithoutExistCheck)) {
            $query->whereNotNull(DB::raw("JSON_EXTRACT(`data`, '$.{$field}')"), $boolean);
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT(`data`, '$.{$field}')) != 'NULL'", [], $boolean);
        }

        match ($operator) {
            // String conditions
            'equals' => $this->applyEquals($query, $field, $value, $boolean),
            'not_equals' => $this->applyNotEquals($query, $field, $value, $boolean),
            'contains' => $this->applyContains($query, $field, $value, $boolean),
            'not_contains' => $this->applyNotContains($query, $field, $value, $boolean),
            'starts_with' => $this->applyStartsWith($query, $field, $value, $boolean),
            'ends_with' => $this->applyEndsWith($query, $field, $value, $boolean),
            'is_set' => $this->applyIsSet($query, $field, $boolean),
            'is_not_set' => $this->applyIsNotSet($query, $field, $boolean),

            // Number conditions
            'greater_than' => $this->applyGreaterThan($query, $field, $value, $boolean),
            'less_than' => $this->applyLessThan($query, $field, $value, $boolean),
            'greater_than_or_equal' => $this->applyGreaterThanOrEqual($query, $field, $value, $boolean),
            'less_than_or_equal' => $this->applyLessThanOrEqual($query, $field, $value, $boolean),
            'between' => $this->applyBetween($query, $field, $value, $boolean),

            // Date conditions
            'expires_in_days_eq' => $this->applyExpiresInDays($query, $field, $value, '=', $boolean),
            'expires_in_days_lte' => $this->applyExpiresInDays($query, $field, $value, '<=', $boolean),
            'expires_within' => $this->applyExpiresWithin($query, $field, $value, $boolean),
            'expired_since' => $this->applyExpiredSince($query, $field, $value, $boolean),
            'equals_date' => $this->applyEqualsDate($query, $field, $value, $boolean),
            'before' => $this->applyBefore($query, $field, $value, $boolean),
            'after' => $this->applyAfter($query, $field, $value, $boolean),

            // Boolean conditions
            'is_true' => $this->applyIsTrue($query, $field, $boolean),
            'is_false' => $this->applyIsFalse($query, $field, $boolean),

            // Enum conditions
            'in' => $this->applyIn($query, $field, $value, $boolean),
            'not_in' => $this->applyNotIn($query, $field, $value, $boolean),

            // Array conditions
            'is_empty' => $this->applyArrayIsEmpty($query, $field, $boolean),
            'not_empty' => $this->applyArrayNotEmpty($query, $field, $boolean),

            default => null
        };
    }

    // ==================== String Conditions ====================

    protected function applyEquals(Builder $query, string $field, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))"), '=', $value, $boolean);
    }

    protected function applyNotEquals(Builder $query, string $field, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))"), '!=', $value, $boolean);
    }

    protected function applyContains(Builder $query, string $field, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))"), 'LIKE', "%{$value}%", $boolean);
    }

    protected function applyNotContains(Builder $query, string $field, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))"), 'NOT LIKE', "%{$value}%", $boolean);
    }

    protected function applyStartsWith(Builder $query, string $field, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))"), 'LIKE', "{$value}%", $boolean);
    }

    protected function applyEndsWith(Builder $query, string $field, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))"), 'LIKE', "%{$value}", $boolean);
    }

    protected function applyIsSet(Builder $query, string $field, string $boolean): void
    {
        $query->whereNotNull(DB::raw("JSON_EXTRACT(`data`, '$.{$field}')"), $boolean);
    }

    protected function applyIsNotSet(Builder $query, string $field, string $boolean): void
    {
        $query->whereNull(DB::raw("JSON_EXTRACT(`data`, '$.{$field}')"), $boolean);
    }

    // ==================== Number Conditions ====================

    protected function applyGreaterThan(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) AS DECIMAL(20,2)) > ?", [$value], $boolean);
    }

    protected function applyLessThan(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) AS DECIMAL(20,2)) < ?", [$value], $boolean);
    }

    protected function applyGreaterThanOrEqual(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) AS DECIMAL(20,2)) >= ?", [$value], $boolean);
    }

    protected function applyLessThanOrEqual(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) AS DECIMAL(20,2)) <= ?", [$value], $boolean);
    }

    protected function applyBetween(Builder $query, string $field, $value, string $boolean): void
    {
        if (!is_array($value) || !isset($value['min']) || !isset($value['max'])) {
            return;
        }

        $query->whereRaw(
            "CAST(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) AS DECIMAL(20,2)) BETWEEN ? AND ?",
            [$value['min'], $value['max']],
            $boolean
        );
    }

    // ==================== Date Conditions ====================

    protected function applyExpiresInDays(Builder $query, string $field, $value, string $operator, string $boolean): void
    {
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        $targetDate = now()->addDays($days)->format('Y-m-d');

        $query->whereRaw(
            "DATE(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))) {$operator} ?",
            [$targetDate],
            $boolean
        );
    }

    protected function applyExpiresWithin(Builder $query, string $field, $value, string $boolean): void
    {
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        if ($days <= 0) {
            return;
        }

        $targetDate = now()->addDays($days)->format('Y-m-d');

        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) BETWEEN CURDATE() AND ?",
            [$targetDate],
            $boolean
        );
    }

    protected function applyExpiredSince(Builder $query, string $field, $value, string $boolean): void
    {
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        if ($days <= 0) {
            return;
        }

        $targetDate = now()->subDays($days)->format('Y-m-d');

        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) BETWEEN ? AND CURDATE()",
            [$targetDate],
            $boolean
        );
    }

    protected function applyEqualsDate(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw(
            "DATE(JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}'))) = ?",
            [$value],
            $boolean
        );
    }

    protected function applyBefore(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) < ?",
            [$value],
            $boolean
        );
    }

    protected function applyAfter(Builder $query, string $field, $value, string $boolean): void
    {
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) > ?",
            [$value],
            $boolean
        );
    }

    // ==================== Boolean Conditions ====================

    protected function applyIsTrue(Builder $query, string $field, string $boolean): void
    {
        $query->whereRaw(
            "JSON_EXTRACT(`data`, '$.{$field}') = true",
            [],
            $boolean
        );
    }

    protected function applyIsFalse(Builder $query, string $field, string $boolean): void
    {
        $query->whereRaw(
            "JSON_EXTRACT(`data`, '$.{$field}') = false",
            [],
            $boolean
        );
    }

    // ==================== Enum Conditions ====================

    protected function applyIn(Builder $query, string $field, $value, string $boolean): void
    {
        $values = (array) $value;
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) IN ({$placeholders})",
            $values,
            $boolean
        );
    }

    protected function applyNotIn(Builder $query, string $field, $value, string $boolean): void
    {
        $values = (array) $value;
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.{$field}')) NOT IN ({$placeholders})",
            $values,
            $boolean
        );
    }

    // ==================== Array Conditions ====================

    protected function applyArrayIsEmpty(Builder $query, string $field, string $boolean): void
    {
        $query->whereRaw(
            "JSON_LENGTH(JSON_EXTRACT(`data`, '$.{$field}')) = 0 OR JSON_EXTRACT(`data`, '$.{$field}') IS NULL",
            [],
            $boolean
        );
    }

    protected function applyArrayNotEmpty(Builder $query, string $field, string $boolean): void
    {
        $query->whereRaw(
            "JSON_LENGTH(JSON_EXTRACT(`data`, '$.{$field}')) > 0",
            [],
            $boolean
        );
    }

    // ==================== Public Query Methods ====================

    /**
     * Get the model class for the target type
     */
    protected function getModelClass(string $targetType): string
    {
        return match ($targetType) {
            'customer' => Customer::class,
            'service' => Service::class,
            default => Customer::class,
        };
    }

    /**
     * Count records matching the filter
     */
    public function countMatches(int $clientId, array $filter, string $targetType = 'customer'): int
    {
        $modelClass = $this->getModelClass($targetType);
        $query = $modelClass::where('client_id', $clientId);
        $this->applyFilters($query, $filter, $targetType);
        return $query->count();
    }

    /**
     * Get records matching the filter
     */
    public function getMatches(int $clientId, array $filter, string $targetType = 'customer', ?int $limit = null): \Illuminate\Support\Collection
    {
        $modelClass = $this->getModelClass($targetType);
        $query = $modelClass::where('client_id', $clientId);
        $this->applyFilters($query, $filter, $targetType);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get query builder for records matching the filter
     */
    public function getMatchesQuery(int $clientId, array $filter, string $targetType = 'customer'): Builder
    {
        $modelClass = $this->getModelClass($targetType);
        $query = $modelClass::where('client_id', $clientId);
        $this->applyFilters($query, $filter, $targetType);
        return $query;
    }

    /**
     * Get the SQL query string with bindings for debugging
     */
    public function getDebugSql(int $clientId, array $filter, string $targetType = 'customer'): string
    {
        $modelClass = $this->getModelClass($targetType);
        $query = $modelClass::where('client_id', $clientId);
        $this->applyFilters($query, $filter, $targetType);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'{$binding}'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }
}
