<?php

namespace Tests\Unit;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * AbstractMainFinanceModel throws on a decimal that is not cast to SafeDecimal, but only
 * when a row happens to hold cents. This checks the declaration instead of the data:
 * every DECIMAL column of every finance model must be cast.
 */
class SafeDecimalCastCoverageTest extends TestCase
{
    public function test_every_decimal_column_of_a_finance_model_is_cast()
    {
        $uncast = [];

        foreach ($this->financeModels() as $model) {
            foreach ($this->decimalColumns($model->getTable()) as $column) {
                if (!$model->hasCast($column)) {
                    $uncast[] = class_basename($model) . '.' . $column;
                }
            }
        }

        $this->assertNotEmpty($this->financeModels(), 'no finance model found, the scan is broken');
        $this->assertSame([], $uncast, 'Cast these columns with SafeDecimalCast');
    }

    /**
     * @return AbstractMainFinanceModel[]
     */
    protected function financeModels(): array
    {
        $dir = realpath(__DIR__ . '/../../src/Models');
        $models = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getRealPath(), strlen($dir) + 1, -4);
            $class = 'Condoedge\\Finance\\Models\\' . str_replace('/', '\\', $relative);

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (!$reflection->isAbstract() && $reflection->isSubclassOf(AbstractMainFinanceModel::class)) {
                $models[] = new $class();
            }
        }

        return $models;
    }

    protected function decimalColumns(string $table): array
    {
        // Aliased: MySQL 8 returns information_schema keys upper-cased
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('data_type', 'decimal')
            ->selectRaw('column_name as name')
            ->pluck('name')
            ->all();
    }
}
