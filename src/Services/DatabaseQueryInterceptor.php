<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Observers\DatabaseIntegrityObserver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Kompo\Auth\Models\Plugins\HasSecurity;

class DatabaseQueryInterceptor
{
    /**
     * The database integrity observer instance
     */
    protected DatabaseIntegrityObserver $observer;

    /**
     * Tables to monitor for integrity checking
     */
    protected static array $monitoredTables = [];

    /**
     * Whether the interceptor is currently enabled
     */
    protected static bool $enabled = false;

    /**
     * Constructor
     */
    public function __construct(DatabaseIntegrityObserver $observer)
    {
        $this->observer = $observer;
    }

    /**
     * Enable the query interceptor
     */
    public function enable(): void
    {
        Event::listen(QueryExecuted::class, [$this, 'handleQueryExecuted']);
    }

    /**
     * Handle executed queries and trigger integrity checking when needed
     */
    public function handleQueryExecuted(QueryExecuted $event): void
    {
        HasSecurity::enterBypassContext();
        $sql = $event->sql;
        $bindings = $event->bindings;

        // Parse the query to determine operation and table
        $operation = static::getOperationType($sql);
        $table = static::getTableName($sql);

        static::$monitoredTables = collect((new Graph(config('kompo-finance.model_integrity_relations')))->getAllNodesBFS())->map(fn ($e) => (new $e())->getTable())->all();

        // Only process queries on monitored tables
        if (!$table || !in_array($table, static::$monitoredTables, true)) {
            return;
        }

        // Only process INSERT, UPDATE, DELETE operations
        if (!in_array($operation, ['insert', 'update', 'delete'], true)) {
            return;
        }

        try {
            $affectedIds = static::extractAffectedIds($sql, $bindings, $table, $operation);

            if (!empty($affectedIds)) {
                // Trigger integrity checking
                $this->observer->handleDatabaseChange($table, $operation, $affectedIds);
            }
        } catch (\Exception $e) {
            Log::error("DatabaseQueryInterceptor error processing query: " . $e->getMessage(), [
                'sql' => $sql,
                'bindings' => $bindings,
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            HasSecurity::exitBypassContext();
        }
    }

    /**
     * Extract the operation type from SQL
     */
    protected static function getOperationType(string $sql): ?string
    {
        $sql = trim(strtolower($sql));

        if (str_starts_with($sql, 'insert')) {
            return 'insert';
        } elseif (str_starts_with($sql, 'update')) {
            return 'update';
        } elseif (str_starts_with($sql, 'delete')) {
            return 'delete';
        }

        return null;
    }

    /**
     * Extract table name from SQL
     */
    protected static function getTableName(string $sql): ?string
    {
        // Normalize SQL
        $sql = preg_replace('/\s+/', ' ', trim(strtolower($sql)));

        // Match INSERT INTO table_name
        if (preg_match('/^insert\s+into\s+`?([^`\s]+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        // Match UPDATE table_name
        if (preg_match('/^update\s+`?([^`\s]+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        // Match DELETE FROM table_name
        if (preg_match('/^delete\s+from\s+`?([^`\s]+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract affected IDs from the query
     */
    protected static function extractAffectedIds(string $sql, array $bindings, string $table, string $operation): array
    {
        $affectedIds = [];

        try {
            switch ($operation) {
                case 'insert':
                    // For inserts, we need to get the last inserted ID(s)
                    $lastId = DB::getPdo()->lastInsertId();
                    if ($lastId) {
                        $affectedIds = [(int) $lastId];
                    }
                    break;

                case 'update':
                case 'delete':
                    // For updates/deletes, try to extract IDs from WHERE clause
                    $affectedIds = static::extractIdsFromWhereClause($sql, $bindings, $table);
                    break;
            }
        } catch (\Exception $e) {
            Log::warning("Could not extract affected IDs from query", [
                'operation' => $operation,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
        }

        return array_filter($affectedIds);
    }

    /**
     * Extract IDs from WHERE clause
     */
    protected static function extractIdsFromWhereClause(string $sql, array $bindings, string $table): array
    {
        $ids = [];

        // Look for WHERE id = ? or WHERE id IN (?)
        if (preg_match('/where\s+(?:`?id`?\s*=\s*\?|`?id`?\s+in\s*\([^)]*\))/i', $sql)) {
            // Simple case: direct ID binding
            foreach ($bindings as $binding) {
                if (is_numeric($binding)) {
                    $ids[] = (int) $binding;
                }
            }
        } else {
            // Complex case: need to execute a SELECT to find affected IDs
            try {
                // Convert UPDATE/DELETE to SELECT to find affected records
                $selectSql = static::convertToSelectIds($sql, $table);
                if ($selectSql) {
                    $results = DB::select($selectSql, $bindings);
                    $ids = array_column($results, 'id');
                }
            } catch (\Exception $e) {
                Log::debug("Could not convert query to SELECT for ID extraction: " . $e->getMessage());
            }
        }

        return array_map('intval', array_unique($ids));
    }

    /**
     * Convert UPDATE/DELETE query to SELECT ID query
     */
    protected static function convertToSelectIds(string $sql, string $table): ?string
    {
        $sql = trim($sql);

        // Extract WHERE clause
        if (preg_match('/\bwhere\b(.+?)(?:\border\s+by\b|\blimit\b|\bgroup\s+by\b|$)/i', $sql, $matches)) {
            $whereClause = trim($matches[1]);
            return "SELECT id FROM `{$table}` WHERE {$whereClause}";
        }

        return null;
    }
}
