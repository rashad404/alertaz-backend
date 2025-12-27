<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SegmentQueryBuilder
{
    /**
     * Apply segment filters to query
     *
     * @param Builder $query
     * @param array $filter
     * @return Builder
     */
    public function applyFilters(Builder $query, array $filter): Builder
    {
        if (empty($filter['conditions'])) {
            return $query;
        }

        $logic = $filter['logic'] ?? 'AND';
        $conditions = $filter['conditions'];

        if ($logic === 'AND') {
            foreach ($conditions as $condition) {
                $this->applyCondition($query, $condition);
            }
        } else {
            // OR logic
            $query->where(function ($q) use ($conditions) {
                foreach ($conditions as $condition) {
                    $this->applyCondition($q, $condition, 'or');
                }
            });
        }

        return $query;
    }

    /**
     * Apply single condition to query
     *
     * @param Builder $query
     * @param array $condition
     * @param string $boolean
     * @return void
     */
    protected function applyCondition(Builder $query, array $condition, string $boolean = 'and'): void
    {
        $key = $condition['key'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        // JSON path for attributes
        $jsonPath = "attributes->{$key}";

        // For most operators (except is_not_set), ensure the attribute exists and is not JSON null
        // This makes filtering by hosting_expiry only return contacts with hosting
        $operatorsWithoutExistCheck = ['is_not_set', 'is_empty'];
        if (!in_array($operator, $operatorsWithoutExistCheck)) {
            $query->whereNotNull(DB::raw("JSON_EXTRACT(`attributes`, '$.{$key}')"), $boolean);
            // Also exclude JSON null values (where key exists but value is null)
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT(`attributes`, '$.{$key}')) != 'NULL'", [], $boolean);
        }

        match ($operator) {
            // String conditions
            'equals' => $this->applyEquals($query, $jsonPath, $value, $boolean),
            'not_equals' => $this->applyNotEquals($query, $jsonPath, $value, $boolean),
            'contains' => $this->applyContains($query, $jsonPath, $value, $boolean),
            'not_contains' => $this->applyNotContains($query, $jsonPath, $value, $boolean),
            'starts_with' => $this->applyStartsWith($query, $jsonPath, $value, $boolean),
            'ends_with' => $this->applyEndsWith($query, $jsonPath, $value, $boolean),
            'is_set' => $this->applyIsSet($query, $jsonPath, $boolean),
            'is_not_set' => $this->applyIsNotSet($query, $jsonPath, $boolean),

            // Number conditions
            'greater_than' => $this->applyGreaterThan($query, $jsonPath, $value, $boolean),
            'less_than' => $this->applyLessThan($query, $jsonPath, $value, $boolean),
            'greater_than_or_equal' => $this->applyGreaterThanOrEqual($query, $jsonPath, $value, $boolean),
            'less_than_or_equal' => $this->applyLessThanOrEqual($query, $jsonPath, $value, $boolean),
            'between' => $this->applyBetween($query, $jsonPath, $value, $boolean),

            // Date conditions (days from now - future)
            'expires_in_days_eq' => $this->applyExpiresInDays($query, $jsonPath, $value, '=', $boolean),
            'expires_in_days_gt' => $this->applyExpiresInDays($query, $jsonPath, $value, '>', $boolean),
            'expires_in_days_gte' => $this->applyExpiresInDays($query, $jsonPath, $value, '>=', $boolean),
            'expires_in_days_lt' => $this->applyExpiresInDays($query, $jsonPath, $value, '<', $boolean),
            'expires_in_days_lte' => $this->applyExpiresInDays($query, $jsonPath, $value, '<=', $boolean),

            // Date conditions (days ago - past)
            'days_ago_eq' => $this->applyDaysAgo($query, $jsonPath, $value, '=', $boolean),
            'days_ago_gt' => $this->applyDaysAgo($query, $jsonPath, $value, '<', $boolean),  // > days ago means date < target
            'days_ago_gte' => $this->applyDaysAgo($query, $jsonPath, $value, '<=', $boolean),
            'days_ago_lt' => $this->applyDaysAgo($query, $jsonPath, $value, '>', $boolean),  // < days ago means date > target
            'days_ago_lte' => $this->applyDaysAgo($query, $jsonPath, $value, '>=', $boolean),

            // Legacy date conditions
            'expires_within' => $this->applyExpiresWithin($query, $jsonPath, $value, $boolean),
            'expired_since' => $this->applyExpiredSince($query, $jsonPath, $value, $boolean),
            'equals_date' => $this->applyEqualsDate($query, $jsonPath, $value, $boolean),
            'before' => $this->applyBefore($query, $jsonPath, $value, $boolean),
            'after' => $this->applyAfter($query, $jsonPath, $value, $boolean),

            // Boolean conditions
            'is_true' => $this->applyIsTrue($query, $jsonPath, $boolean),
            'is_false' => $this->applyIsFalse($query, $jsonPath, $boolean),

            // Enum conditions
            'in' => $this->applyIn($query, $jsonPath, $value, $boolean),
            'not_in' => $this->applyNotIn($query, $jsonPath, $value, $boolean),

            // Array conditions
            'count' => $this->applyArrayCount($query, $jsonPath, $value, $boolean),
            'not_empty' => $this->applyArrayNotEmpty($query, $jsonPath, $boolean),
            'is_empty' => $this->applyArrayIsEmpty($query, $jsonPath, $boolean),
            'any_expiry_within' => $this->applyArrayAnyExpiryWithin($query, $jsonPath, $value, $boolean),
            'any_expiry_expired_since' => $this->applyArrayAnyExpiryExpiredSince($query, $jsonPath, $value, $boolean),
            'any_expiry_today' => $this->applyArrayAnyExpiryToday($query, $jsonPath, $boolean),
            'any' => $this->applyArrayAny($query, $jsonPath, $value, $boolean),
            'all' => $this->applyArrayAll($query, $jsonPath, $value, $boolean),
            'none' => $this->applyArrayNone($query, $jsonPath, $value, $boolean),
            'exists' => $this->applyArrayExists($query, $jsonPath, $value, $boolean),

            default => null
        };
    }

    // ==================== String Conditions ====================

    protected function applyEquals(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))"), '=', $value, $boolean);
    }

    protected function applyNotEquals(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))"), '!=', $value, $boolean);
    }

    protected function applyContains(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))"), 'LIKE', "%{$value}%", $boolean);
    }

    protected function applyNotContains(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))"), 'NOT LIKE', "%{$value}%", $boolean);
    }

    protected function applyStartsWith(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))"), 'LIKE', "{$value}%", $boolean);
    }

    protected function applyEndsWith(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))"), 'LIKE', "%{$value}", $boolean);
    }

    protected function applyIsSet(Builder $query, string $jsonPath, string $boolean): void
    {
        $query->whereNotNull(DB::raw("JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')"), $boolean);
    }

    protected function applyIsNotSet(Builder $query, string $jsonPath, string $boolean): void
    {
        $query->whereNull(DB::raw("JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')"), $boolean);
    }

    // ==================== Number Conditions ====================

    protected function applyGreaterThan(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) AS DECIMAL(20,2)) > ?", [$value], $boolean);
    }

    protected function applyLessThan(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) AS DECIMAL(20,2)) < ?", [$value], $boolean);
    }

    protected function applyGreaterThanOrEqual(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) AS DECIMAL(20,2)) >= ?", [$value], $boolean);
    }

    protected function applyLessThanOrEqual(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) AS DECIMAL(20,2)) <= ?", [$value], $boolean);
    }

    protected function applyBetween(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        if (!is_array($value) || !isset($value['min']) || !isset($value['max'])) {
            return;
        }

        $query->whereRaw(
            "CAST(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) AS DECIMAL(20,2)) BETWEEN ? AND ?",
            [$value['min'], $value['max']],
            $boolean
        );
    }

    // ==================== Date Conditions ====================

    /**
     * Apply date comparison based on days from now
     * Compares the date attribute to (today + X days)
     */
    protected function applyExpiresInDays(Builder $query, string $jsonPath, $value, string $operator, string $boolean): void
    {
        // Handle both formats: {"days": 3} or just the number 3
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        $targetDate = now()->addDays($days)->format('Y-m-d');

        $query->whereRaw(
            "DATE(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))) {$operator} ?",
            [$targetDate],
            $boolean
        );
    }

    /**
     * Apply date comparison based on days ago
     * Compares the date attribute to (today - X days)
     */
    protected function applyDaysAgo(Builder $query, string $jsonPath, $value, string $operator, string $boolean): void
    {
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        $targetDate = now()->subDays($days)->format('Y-m-d');

        $query->whereRaw(
            "DATE(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))) {$operator} ?",
            [$targetDate],
            $boolean
        );
    }

    protected function applyExpiresWithin(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        // Handle both formats: {"days": 3} or just the number 3
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            // Invalid value, skip this condition
            return;
        }

        if ($days <= 0) {
            return;
        }

        $targetDate = now()->addDays($days)->format('Y-m-d');

        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) BETWEEN CURDATE() AND ?",
            [$targetDate],
            $boolean
        );
    }

    protected function applyExpiredSince(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        // Handle both formats: {"days": 3} or just the number 3
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            // Invalid value, skip this condition
            return;
        }

        if ($days <= 0) {
            return;
        }

        $targetDate = now()->subDays($days)->format('Y-m-d');

        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) BETWEEN ? AND CURDATE()",
            [$targetDate],
            $boolean
        );
    }

    protected function applyEqualsDate(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw(
            "DATE(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'))) = ?",
            [$value],
            $boolean
        );
    }

    protected function applyBefore(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) < ?",
            [$value],
            $boolean
        );
    }

    protected function applyAfter(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) > ?",
            [$value],
            $boolean
        );
    }

    // ==================== Boolean Conditions ====================

    protected function applyIsTrue(Builder $query, string $jsonPath, string $boolean): void
    {
        $query->whereRaw(
            "JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}') = true",
            [],
            $boolean
        );
    }

    protected function applyIsFalse(Builder $query, string $jsonPath, string $boolean): void
    {
        $query->whereRaw(
            "JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}') = false",
            [],
            $boolean
        );
    }

    // ==================== Enum Conditions ====================

    protected function applyIn(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $placeholders = implode(',', array_fill(0, count($value), '?'));
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) IN ({$placeholders})",
            $value,
            $boolean
        );
    }

    protected function applyNotIn(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        $placeholders = implode(',', array_fill(0, count($value), '?'));
        $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) NOT IN ({$placeholders})",
            $value,
            $boolean
        );
    }

    // ==================== Array Conditions ====================

    protected function applyArrayCount(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        if (!is_array($value) || !isset($value['operator']) || !isset($value['count'])) {
            return;
        }

        $operator = $value['operator']; // equals, greater_than, less_than
        $count = $value['count'];

        $sqlOperator = match ($operator) {
            'equals' => '=',
            'greater_than' => '>',
            'less_than' => '<',
            'greater_than_or_equal' => '>=',
            'less_than_or_equal' => '<=',
            default => '='
        };

        $query->whereRaw(
            "JSON_LENGTH(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) {$sqlOperator} ?",
            [$count],
            $boolean
        );
    }

    protected function applyArrayAny(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        // Check if any element in array matches the value
        $query->whereRaw(
            "JSON_CONTAINS(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'), ?)",
            [json_encode($value)],
            $boolean
        );
    }

    protected function applyArrayAll(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        // Check if all values exist in the array
        foreach ($value as $item) {
            $query->whereRaw(
                "JSON_CONTAINS(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'), ?)",
                [json_encode($item)],
                $boolean
            );
        }
    }

    protected function applyArrayNone(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        // Check that none of the values exist in the array
        foreach ($value as $item) {
            $query->whereRaw(
                "NOT JSON_CONTAINS(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}'), ?)",
                [json_encode($item)],
                $boolean
            );
        }
    }

    protected function applyArrayExists(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        // Check if specific value exists in array (alias for 'any')
        $this->applyArrayAny($query, $jsonPath, $value, $boolean);
    }

    protected function applyArrayIsEmpty(Builder $query, string $jsonPath, string $boolean): void
    {
        $query->whereRaw(
            "JSON_LENGTH(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) = 0 OR JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}') IS NULL",
            [],
            $boolean
        );
    }

    protected function applyArrayNotEmpty(Builder $query, string $jsonPath, string $boolean): void
    {
        $query->whereRaw(
            "JSON_LENGTH(JSON_EXTRACT(`attributes`, '$.{$this->extractKey($jsonPath)}')) > 0",
            [],
            $boolean
        );
    }

    /**
     * Check if any item in array of objects has expiry within X days from now
     * Works with arrays like: [{name: "example.com", expiry: "2025-01-15"}, ...]
     */
    protected function applyArrayAnyExpiryWithin(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        $key = $this->extractKey($jsonPath);
        $today = now()->format('Y-m-d');
        $targetDate = now()->addDays($days)->format('Y-m-d');

        // Use JSON_TABLE to check if any item's expiry is between today and target date
        // This works on MySQL 8.0+
        $query->whereRaw(
            "EXISTS (
                SELECT 1 FROM JSON_TABLE(
                    JSON_EXTRACT(`attributes`, '$.{$key}'),
                    '\$[*]' COLUMNS (
                        expiry VARCHAR(20) PATH '\$.expiry'
                    )
                ) AS jt
                WHERE jt.expiry BETWEEN ? AND ?
            )",
            [$today, $targetDate],
            $boolean
        );
    }

    /**
     * Check if any item in array of objects has expiry that expired in the last X days
     */
    protected function applyArrayAnyExpiryExpiredSince(Builder $query, string $jsonPath, $value, string $boolean): void
    {
        if (is_array($value) && isset($value['days'])) {
            $days = (int) $value['days'];
        } elseif (is_numeric($value)) {
            $days = (int) $value;
        } else {
            return;
        }

        $key = $this->extractKey($jsonPath);
        $today = now()->format('Y-m-d');
        $pastDate = now()->subDays($days)->format('Y-m-d');

        $query->whereRaw(
            "EXISTS (
                SELECT 1 FROM JSON_TABLE(
                    JSON_EXTRACT(`attributes`, '$.{$key}'),
                    '\$[*]' COLUMNS (
                        expiry VARCHAR(20) PATH '\$.expiry'
                    )
                ) AS jt
                WHERE jt.expiry BETWEEN ? AND ?
            )",
            [$pastDate, $today],
            $boolean
        );
    }

    /**
     * Check if any item in array of objects has expiry today
     */
    protected function applyArrayAnyExpiryToday(Builder $query, string $jsonPath, string $boolean): void
    {
        $key = $this->extractKey($jsonPath);
        $today = now()->format('Y-m-d');

        $query->whereRaw(
            "EXISTS (
                SELECT 1 FROM JSON_TABLE(
                    JSON_EXTRACT(`attributes`, '$.{$key}'),
                    '\$[*]' COLUMNS (
                        expiry VARCHAR(20) PATH '\$.expiry'
                    )
                ) AS jt
                WHERE jt.expiry = ?
            )",
            [$today],
            $boolean
        );
    }

    // ==================== Helper Methods ====================

    /**
     * Extract key from JSON path
     *
     * @param string $jsonPath
     * @return string
     */
    protected function extractKey(string $jsonPath): string
    {
        // Extract key from "attributes->key" format
        return str_replace('attributes->', '', $jsonPath);
    }

    /**
     * Count contacts matching the filter
     *
     * @param int $clientId
     * @param array $filter
     * @return int
     */
    public function countMatches(int $clientId, array $filter): int
    {
        $query = \App\Models\Contact::where('client_id', $clientId);
        $this->applyFilters($query, $filter);
        return $query->count();
    }

    /**
     * Get contacts matching the filter
     *
     * @param int $clientId
     * @param array $filter
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getMatches(int $clientId, array $filter, ?int $limit = null): \Illuminate\Support\Collection
    {
        $query = \App\Models\Contact::where('client_id', $clientId);
        $this->applyFilters($query, $filter);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get query builder for contacts matching the filter
     * Use this with chunk() for large datasets
     *
     * @param int $clientId
     * @param array $filter
     * @return Builder
     */
    public function getMatchesQuery(int $clientId, array $filter): Builder
    {
        $query = \App\Models\Contact::where('client_id', $clientId);
        $this->applyFilters($query, $filter);
        return $query;
    }
}
