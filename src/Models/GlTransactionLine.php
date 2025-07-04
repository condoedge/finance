<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Finance\Casts\SafeDecimalCast;

class GlTransactionLine extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    
    protected $table = 'fin_gl_transaction_lines';

    protected $casts = [
        'debit_amount' => SafeDecimalCast::class,
        'credit_amount' => SafeDecimalCast::class,
    ];

    public static function boot()
    {
        parent::boot();
        
        // Ensure integrity checks are applied on create/update/delete        
        static::updating(function ($model) {
            if ($model->header->is_posted) {
                throw new \Exception(__('error-cannot-modify-posted-transaction'));
            }
        });
        
        static::deleting(function ($model) {
            if ($model->header->is_posted) {
                throw new \Exception(__('error-cannot-delete-posted-transaction'));
            }
        });
    }
    
    /**
     * Relationships
     */
    public function header()
    {
        return $this->belongsTo(GlTransactionHeader::class, 'gl_transaction_id');
    }
    
    public function account()
    {
        return $this->belongsTo(GlAccount::class, 'account_id');
    }
    
    /**
     * No calculated columns for this model - validation handled by triggers
     */
    public static function columnsIntegrityCalculations()
    {
        return [];
    }
    
    /**
     * Scope for lines with debit amounts
     */
    public function scopeDebits($query)
    {
        return $query->where('debit_amount', '>', 0);
    }
    
    /**
     * Scope for lines with credit amounts
     */
    public function scopeCredits($query)
    {
        return $query->where('credit_amount', '>', 0);
    }
}
