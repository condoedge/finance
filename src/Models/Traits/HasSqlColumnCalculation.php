<?php

namespace Condoedge\Finance\Models\Traits;

trait HasSqlColumnCalculation 
{
    public function getSqlColumnCalculation($column, $alias = null)
    {
        return $this->newQuery()
            ->selectRaw($column . ' as ' . ($alias ?? $column))
            ->where('id', $this->id)
            ->value($alias ?? $column);
    }
}