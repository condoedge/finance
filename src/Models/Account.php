<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\GlAccountService;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Utils\Models\Model;

class Account extends Model
{
    use HasIntegrityCheck;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_gl_accounts';
    
    protected $casts = [
        'is_active' => 'boolean',
        'allow_manual_entry' => 'boolean',
        'account_type' => AccountTypeEnum::class,
    ];
    
    /**
     * Relationships
     */
    public function glTransactionLines()
    {
        return $this->hasMany(GlTransactionLine::class, 'account_id', 'account_id');
    }
    
    /**
     * Get the segments for this account
     */
    public function getSegmentsAttribute(): array
    {
        return GlAccountSegment::parseAccountId($this->account_id);
    }
    
    /**
     * Get detailed segment information
     */
    public function getSegmentDetailsAttribute(): \Illuminate\Support\Collection
    {
        $segments = $this->segments;
        $details = collect();
        
        foreach ($segments as $position => $value) {
            $segmentNumber = $position + 1;
            $description = GlAccountSegment::getSegmentDescription($segmentNumber, $value, $this->team_id);
            
            $details->push((object) [
                'segment_number' => $segmentNumber,
                'segment_value' => $value,
                'segment_description' => $description,
            ]);
        }
        
        return $details;
    }
    
    /**
     * Get formatted account display
     */
    public function getFormattedAccountIdAttribute(): string
    {
        return $this->account_id; // Already formatted as XX-XXX-XXXX
    }
    
    /**
     * Get auto-generated description from segments
     */
    public function getAutoDescriptionAttribute(): string
    {
        $segmentService = app(\Condoedge\Finance\Services\GlSegmentService::class);
        return $segmentService->getAccountDescription($this->account_id, $this->team_id);
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
        return in_array($this->account_type, [self::TYPE_ASSET, self::TYPE_EXPENSE]);
    }
    
    /**
     * Check if this is a normal credit account
     */
    public function isNormalCreditAccount(): bool
    {
        return in_array($this->account_type, [self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_REVENUE]);
    }
    
    /**
     * Validate account ID format and segment values
     */
    public static function validateAccountId(string $accountId, int $teamId): bool
    {
        return GlAccountService::validateAccountId($accountId, $teamId);
    }
    
    /**
     * Create account with validation
     */
    public static function createWithValidation(array $attributes): self
    {
        return GlAccountService::createAccount($attributes);
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
     * No calculated columns for accounts
     */
    public static function columnsIntegrityCalculations()
    {
        return [];
    }
}