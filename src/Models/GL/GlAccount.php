<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;
use Condoedge\Finance\Models\Account;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlAccount extends Account
{
    protected $table = 'fin_accounts';

    protected $fillable = [
        'account_id',
        'account_description',
        'is_active',
        'allow_manual_entry',
        'segment1_value',
        'segment2_value',
        'segment3_value',
        'segment4_value',
        'segment5_value',
        'account_type',
        'account_category'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_manual_entry' => 'boolean'
    ];

    // Account types
    const TYPE_ASSET = 'Asset';
    const TYPE_LIABILITY = 'Liability';
    const TYPE_EQUITY = 'Equity';
    const TYPE_REVENUE = 'Revenue';
    const TYPE_EXPENSE = 'Expense';

    /**
     * Get GL entries for this account
     */
    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'account_id', 'account_id');
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for accounts that allow manual entry
     */
    public function scopeManualEntryAllowed($query)
    {
        return $query->where('allow_manual_entry', true);
    }

    /**
     * Scope by account type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Get account balance
     */
    public function getBalance($asOfDate = null)
    {
        $query = $this->glEntries();
        
        if ($asOfDate) {
            $query->whereHas('glTransaction', function($q) use ($asOfDate) {
                $q->where('fiscal_date', '<=', $asOfDate);
            });
        }

        $debits = $query->sum('debit_amount');
        $credits = $query->sum('credit_amount');

        // For asset and expense accounts, debit increases balance
        // For liability, equity, and revenue accounts, credit increases balance
        if (in_array($this->account_type, [self::TYPE_ASSET, self::TYPE_EXPENSE])) {
            return $debits - $credits;
        } else {
            return $credits - $debits;
        }
    }

    /**
     * Check if account can be used in manual GL transactions
     */
    public function canBeUsedInManualTransactions(): bool
    {
        return $this->is_active && $this->allow_manual_entry;
    }

    /**
     * Get full account description with segments
     */
    public function getFullDescription(): string
    {
        $segments = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $segmentValue = $this->{"segment{$i}_value"};
            if ($segmentValue) {
                $description = GlSegmentValue::getSegmentDescription($i, $segmentValue);
                $segments[] = $description ?: $segmentValue;
            }
        }

        return $this->account_description . ($segments ? ' (' . implode(' - ', $segments) . ')' : '');
    }

    /**
     * Validate account structure
     */
    public function validateAccountStructure(): array
    {
        return AccountSegmentDefinition::validateAccountStructure($this->account_id);
    }

    /**
     * Create account from segments
     */
    public static function createFromSegments(array $segments, string $description, string $type = null): static
    {
        $accountId = implode('-', $segments);
        
        // Validate structure
        $errors = AccountSegmentDefinition::validateAccountStructure($accountId);
        if (!empty($errors)) {
            throw new \Exception('Invalid account structure: ' . implode(', ', $errors));
        }

        $data = [
            'account_id' => $accountId,
            'account_description' => $description,
            'account_type' => $type,
            'is_active' => true,
            'allow_manual_entry' => true
        ];

        // Set segment values
        foreach ($segments as $index => $value) {
            $data["segment" . ($index + 1) . "_value"] = $value;
        }

        return static::create($data);
    }

    /**
     * Get accounts by segment value
     */
    public static function getBySegment(int $position, string $value)
    {
        $field = "segment{$position}_value";
        return static::where($field, $value)->active()->get();
    }

    /**
     * Get account types
     */
    public static function getAccountTypes(): array
    {
        return [
            self::TYPE_ASSET,
            self::TYPE_LIABILITY,
            self::TYPE_EQUITY,
            self::TYPE_REVENUE,
            self::TYPE_EXPENSE
        ];
    }
}
