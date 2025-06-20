<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\GlAccountService;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\DB;

class GlAccount extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_gl_accounts';
    
    protected $casts = [
        'is_active' => 'boolean',
        'allow_manual_entry' => 'boolean',
        'account_type' => AccountTypeEnum::class,
    ];
    
    protected $fillable = [
        'account_id',
        'account_description',
        'account_segments_descriptor',
        'account_type',
        'is_active',
        'allow_manual_entry',
        'team_id',
    ];
    
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
    
    public function scopeAllowManualEntry($query)
    {
        return $query->where('allow_manual_entry', true);
    }
    
    public function scopeByType($query, string $accountType)
    {
        return $query->where('account_type', $accountType);
    }
    
    public function scopeForTeam($query, ?int $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }
    
    public function scopeWithSegmentValue($query, int $segmentValueId)
    {
        return $query->whereHas('segmentAssignments', function ($q) use ($segmentValueId) {
            $q->where('segment_value_id', $segmentValueId);
        });
    }
    
    /**
     * Create account from segment value IDs
     * 
     * @param array $segmentValueIds Array of segment value IDs
     * @param array $attributes Additional account attributes
     */
    public static function createFromSegmentValues(array $segmentValueIds, array $attributes): self
    {
        return DB::transaction(function () use ($segmentValueIds, $attributes) {
            // Validate segment combination
            if (!AccountSegmentAssignment::validateSegmentValueCombination($segmentValueIds)) {
                throw new \InvalidArgumentException('Invalid segment combination - missing required segments');
            }
            
            // Check if account already exists
            $existingAccounts = AccountSegmentAssignment::findAccountsBySegmentValues(
                $segmentValueIds, 
                $attributes['team_id']
            );
            
            if ($existingAccounts->isNotEmpty()) {
                return $existingAccounts->first();
            }
            
            // Create account with temporary values (will be updated by trigger)
            $account = static::create(array_merge($attributes, [
                'account_id' => 'TEMP-' . uniqid(),
                'account_segments_descriptor' => 'TEMP',
            ]));
            
            // Create segment assignments
            AccountSegmentAssignment::createForAccount($account->id, $segmentValueIds);
            
            // Trigger will update computed fields, refresh to get them
            return $account->refresh();
        });
    }
    
    /**
     * Update the last segment value of an account
     * Used in Chart of Accounts for editing
     */
    public function updateLastSegmentValue(int $newSegmentValueId): self
    {
        return DB::transaction(function () use ($newSegmentValueId) {
            $newSegmentValue = SegmentValue::findOrFail($newSegmentValueId);
            
            // Get current segment values
            $currentValues = $this->orderedSegmentValues;
            if ($currentValues->isEmpty()) {
                throw new \InvalidArgumentException('Account has no segments');
            }
            
            // Validate new value is for the same position as last segment
            $lastSegment = $currentValues->last();
            if ($newSegmentValue->segment_definition_id !== $lastSegment->segment_definition_id) {
                throw new \InvalidArgumentException('New segment value must be for the same segment definition');
            }
            
            // Build new segment value IDs array
            $newSegmentValueIds = $currentValues
                ->slice(0, -1) // All except last
                ->pluck('id')
                ->push($newSegmentValueId)
                ->values()
                ->toArray();
            
            // Update assignments
            AccountSegmentAssignment::createForAccount($this->id, $newSegmentValueIds);
            
            // Refresh to get updated computed fields from trigger
            return $this->refresh();
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
        return in_array($this->account_type->value, ['ASSET', 'EXPENSE']);
    }
    
    /**
     * Check if this is a normal credit account
     */
    public function isNormalCreditAccount(): bool
    {
        return in_array($this->account_type->value, ['LIABILITY', 'EQUITY', 'REVENUE']);
    }
    
    /**
     * Get account balance for a specific date range
     */
    public function getBalance(?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): float
    {
        $balance = GlAccountService::getAccountBalance($this, $startDate, $endDate);
        return (float) $balance->toFloat();
    }
    
    /**
     * Force refresh computed fields from database functions
     */
    public function refreshComputedFields(): self
    {
        DB::statement(
            'UPDATE fin_gl_accounts SET 
                account_id = build_account_id(id),
                account_segments_descriptor = build_account_descriptor(id)
            WHERE id = ?',
            [$this->id]
        );
        
        return $this->refresh();
    }

    
    /**
     * Columns for integrity check (none for accounts)
     */
    public static function columnsIntegrityCalculations()
    {
        return [
            'account_segments_descriptor' => 'build_account_descriptor(fin_gl_accounts.id)',
        ];
    }
}
