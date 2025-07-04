<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Facades\GlTransactionService;

class GlAccount extends AbstractMainFinanceModel
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    protected $table = 'fin_gl_accounts';

    protected $casts = [
        'is_active' => 'boolean',
        'allow_manual_entry' => 'boolean',
    ];

    // Removed fillable - using property assignment instead

    /**
     * Relationships
     */
    public function glTransactionLines()
    {
        return $this->hasMany(GlTransactionLine::class, 'account_id', 'account_id');
    }

    /**
     * Get segment assignments for this account
     */
    public function segmentAssignments()
    {
        return $this->hasMany(AccountSegmentAssignment::class, 'account_id', 'id');
    }

    public function lastSegmentValue()
    {
        return $this->hasOneThrough(
            SegmentValue::class,
            AccountSegmentAssignment::class,
            'account_id',
            'id',
            'id',
            'segment_value_id',
        )->latest();
    }

    /**
     * Get segment values for this account through assignments
     */
    public function segmentValues()
    {
        return $this->belongsToMany(
            SegmentValue::class,
            'fin_account_segment_assignments',
            'account_id',
            'segment_value_id'
        )->with('segmentDefinition');
    }

    /**
     * Get ordered segment values with their definitions
     */
    public function getOrderedSegmentValuesAttribute()
    {
        return $this->segmentValues()
            ->get()
            ->sortBy('segmentDefinition.segment_position')
            ->values();
    }

    /**
     * Get the last segment value (for editing)
     */
    public function getLastSegmentValueAttribute()
    {
        return $this->orderedSegmentValues->last();
    }

    public function getAccountTypeAttribute()
    {
        return $this->lastSegmentValue->account_type;
    }

    /**
     * Get detailed segment information
     */
    public function getSegmentDetailsAttribute(): \Illuminate\Support\Collection
    {
        return $this->orderedSegmentValues->map(function ($segmentValue) {
            return (object) [
                'position' => $segmentValue->segmentDefinition->segment_position,
                'value' => $segmentValue->segment_value,
                'value_description' => $segmentValue->segment_description,
                'definition_description' => $segmentValue->segmentDefinition->segment_description,
                'segment_value_id' => $segmentValue->id,
            ];
        });
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->withTrashed()->where('is_active', 0);
    }

    public function scopeAllowManualEntry($query)
    {
        return $query->where('allow_manual_entry', true);
    }

    public function scopeByType($query, string $accountType)
    {
        return $query->where('account_type', $accountType);
    }

    public function scopeWithSegmentValue($query, int $segmentValueId)
    {
        return $query->whereHas('segmentAssignments', function ($q) use ($segmentValueId) {
            $q->where('segment_value_id', $segmentValueId);
        });
    }

    public function scopeByNaturalAccount($query, string $naturalAccountId)
    {
        return $query->whereHas('lastSegmentValue', function ($q) use ($naturalAccountId) {
            $q->where('id', $naturalAccountId);
        });
    }

    /**
     * Check if account allows manual entries
     */
    public function allowsManualEntry(): bool
    {
        return $this->is_active && $this->allow_manual_entry;
    }

    /**
     * Check if this is a normal debit account
     */
    public function isNormalDebitAccount(): bool
    {
        return in_array($this->lastSegmentValue?->account_type, [AccountTypeEnum::ASSET, AccountTypeEnum::EXPENSE], true);
    }

    /**
     * Check if this is a normal credit account
     */
    public function isNormalCreditAccount(): bool
    {
        return in_array($this->lastSegmentValue?->account_type, [AccountTypeEnum::LIABILITY, AccountTypeEnum::EQUITY, AccountTypeEnum::REVENUE], true);
    }

    /**
     * Get account balance for a specific date range
     */
    public function getBalance(?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): SafeDecimal
    {
        $balance = GlTransactionService::getAccountBalance($this, $startDate, $endDate);

        return $balance;
    }

    public static function getFromLatestSegmentValue($valueId)
    {
        return AccountSegmentService::createAccountFromLastValue($valueId);
    }

    public function getLastSegmentValue()
    {
        return $this->orderedSegmentValues->last();
    }

    public function getDisplayAttribute()
    {
        return $this->getLastSegmentValue()?->display;
    }

    /**
     * Columns for integrity check (none for accounts)
     */
    public static function columnsIntegrityCalculations()
    {
        return [
            'account_segments_descriptor' => \DB::raw('build_account_descriptor(fin_gl_accounts.id)'),
        ];
    }
}
