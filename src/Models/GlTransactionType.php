<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Utils\Models\Model;

/**
 * GL Transaction Type Model
 * 
 * Table-linked enum for GL transaction types following the established pattern.
 * This provides referential integrity and allows for future extensibility.
 */
class GlTransactionType extends Model
{
    protected $table = 'fin_gl_transaction_types';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'name',
        'label', 
        'code',
        'fiscal_period_field',
        'allows_manual_entry',
        'description',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'allows_manual_entry' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the corresponding enum instance
     */
    public function getEnum(): ?GlTransactionTypeEnum
    {
        return GlTransactionTypeEnum::tryFrom($this->id);
    }

    /**
     * Scope to only active transaction types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Check if this transaction type allows manual account entries
     */
    public function allowsManualAccountEntry(): bool
    {
        return $this->allows_manual_entry;
    }

    /**
     * Get the fiscal period field name for checking if open
     */
    public function getFiscalPeriodOpenField(): string
    {
        return $this->fiscal_period_field;
    }
}
