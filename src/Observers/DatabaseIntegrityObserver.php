<?php

namespace Condoedge\Finance\Observers;

use Condoedge\Finance\Facades\IntegrityChecker;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceApply;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Services\Graph;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseIntegrityObserver
{
    /**
     * Mapping of table names to their corresponding model classes
     */
    protected static array $tableModelMap = [

    ];

    /**
     * Track if we're currently processing to avoid infinite loops
     */
    protected static bool $isProcessing = false;

    /**
     * Handle database changes for integrity checking
     *
     * @param string $table
     * @param string $operation (insert, update, delete)
     * @param array $affectedIds
     * @param array $data Optional data for context
     */
    public static function handleDatabaseChange(string $table, string $operation, array $affectedIds, array $data = []): void
    {
        // Avoid infinite loops
        if (static::$isProcessing) {
            return;
        }

        static::$tableModelMap = collect((new Graph(config('kompo-finance.model_integrity_relations')))->getAllNodesBFS())
            ->mapWithKeys(fn($e) => [(new $e)->getTable() => $e])->all();

        $modelClass = static::$tableModelMap[$table];

        try {
            static::$isProcessing = true;

            // Execute integrity checking based on operation type
            switch ($operation) {
                case 'insert':
                case 'update':
                    static::handleInsertOrUpdate($modelClass, $affectedIds, $data);
                    break;
                
                case 'delete':
                    static::handleDelete($modelClass, $affectedIds, $data);
                    break;
            }

        } catch (\Exception $e) {
            Log::error("DatabaseIntegrityObserver error: " . $e->getMessage(), [
                'table' => $table,
                'operation' => $operation,
                'affected_ids' => $affectedIds,
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            static::$isProcessing = false;
        }
    }

    /**
     * Handle insert or update operations
     */
    protected static function handleInsertOrUpdate(string $modelClass, array $affectedIds, array $data): void
    {
        // Check integrity for affected models and their relationships
        IntegrityChecker::checkChildrenThenModel($modelClass, $affectedIds);
        IntegrityChecker::checkModelThenParents($modelClass, $affectedIds);
    }

    /**
     * Handle delete operations
     */
    protected static function handleDelete(string $modelClass, array $affectedIds, array $data): void
    {
        IntegrityChecker::checkChildrenThenModel($modelClass, $affectedIds);
        IntegrityChecker::checkModelThenParents($modelClass, $affectedIds);
    }

    /**
     * Register the observer to be called automatically
     */
    public static function register(): void
    {
        // This would be called from a service provider
        // We'll implement the actual listening mechanism next
    }
}
