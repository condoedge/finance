<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Facades\DB;

class GlTransactionLine extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    
    protected $table = 'fin_gl_transaction_lines';
    protected $primaryKey = 'gl_transaction_line_id';
    
    protected $fillable = [
        'gl_transaction_id',
        'account_id',
        'line_description',
        'debit_amount',
        'credit_amount',
        'line_sequence',
    ];
    
    protected $casts = [
        'debit_amount' => SafeDecimal::class,
        'credit_amount' => SafeDecimal::class,
        'line_sequence' => 'integer',
    ];
    
    /**
     * Relationships
     */
    public function header()
    {
        return $this->belongsTo(GlTransactionHeader::class, 'gl_transaction_id', 'gl_transaction_id');
    }
    
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
    
    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-set line sequence
        static::creating(function ($line) {
            if (!$line->line_sequence) {
                $maxSequence = static::where('gl_transaction_id', $line->gl_transaction_id)
                                   ->max('line_sequence') ?? 0;
                $line->line_sequence = $maxSequence + 1;
            }
        });
    }
    
    /**
     * Validate amounts (ensure only debit OR credit)
     */
    public function setDebitAmountAttribute($value)
    {
        if ($value > 0 && $this->credit_amount > 0) {
            throw new \Exception('Cannot have both debit and credit amounts');
        }
        $this->attributes['debit_amount'] = $value;
    }
    
    public function setCreditAmountAttribute($value)
    {
        if ($value > 0 && $this->debit_amount > 0) {
            throw new \Exception('Cannot have both debit and credit amounts');
        }
        $this->attributes['credit_amount'] = $value;
    }
    
    /**
     * Get net amount (debit positive, credit negative)
     */
    public function getNetAmountAttribute(): SafeDecimal
    {
        return new SafeDecimal($this->debit_amount - $this->credit_amount);
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
