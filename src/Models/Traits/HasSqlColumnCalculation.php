<?php

namespace Condoedge\Finance\Models\Traits;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;

trait HasSqlColumnCalculation
{
    public function getSqlColumnCalculation($column, $alias = null)
    {
        $column = $column instanceof Expression ? $column->getValue(new Grammar()) : $column;

        return $this->newQuery()
            ->selectRaw($column . ' as ' . ($alias ?? $column))
            ->where('id', $this->id)
            ->value($alias ?? $column);
    }
}
