<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends AbstractMainFinanceModel
{
    protected $table = 'fin_vendors';

    protected $fillable = [
        'vendor_code',
        'vendor_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'tax_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get GL transactions for this vendor
     */
    public function glTransactions(): HasMany
    {
        return $this->hasMany(GlTransaction::class);
    }

    /**
     * Scope for active vendors
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get vendor balance (payables)
     */
    public function getBalance($asOfDate = null)
    {
        $query = $this->glTransactions();
        
        if ($asOfDate) {
            $query->where('fiscal_date', '<=', $asOfDate);
        }

        return $query->with('glEntries')->get()->sum(function($transaction) {
            return $transaction->glEntries->where('glAccount.account_type', GlAccount::TYPE_LIABILITY)->sum('credit_amount') -
                   $transaction->glEntries->where('glAccount.account_type', GlAccount::TYPE_LIABILITY)->sum('debit_amount');
        });
    }

    /**
     * Create vendor with unique code
     */
    public static function createWithUniqueCode(array $data): static
    {
        if (!isset($data['vendor_code'])) {
            $data['vendor_code'] = static::generateUniqueCode($data['vendor_name'] ?? 'VENDOR');
        }

        return static::create($data);
    }

    /**
     * Generate unique vendor code
     */
    public static function generateUniqueCode(string $baseName): string
    {
        $code = strtoupper(substr($baseName, 0, 6));
        $counter = 1;
        
        while (static::where('vendor_code', $code)->exists()) {
            $code = strtoupper(substr($baseName, 0, 4)) . sprintf('%02d', $counter);
            $counter++;
        }

        return $code;
    }
}
