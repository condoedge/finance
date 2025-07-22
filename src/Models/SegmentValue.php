<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Utils\Models\Model;

/**
 * Segment Value Model
 *
 * Stores reusable segment values that can be shared across different accounts
 * Example: segment_value="10" with description="parent_team_10" for position 1
 *
 * @property int $id
 * @property int $segment_definition_id References fin_account_segments
 * @property string $segment_value The actual code ('10', '03', '4000')
 * @property string $segment_description Human-readable description
 * @property bool $is_active
 * @property bool $allow_manual_entry Indicates if manual entry is allowed for this segment value
 * @property AccountTypeEnum|null $account_type Type of account this segment value belongs to (e.g. asset, liability)
 */
class SegmentValue extends AbstractMainFinanceModel
{
    protected $table = 'fin_segment_values';

    // Removed fillable - using property assignment instead

    protected $casts = [
        'segment_definition_id' => 'integer',
        'is_active' => 'boolean',
        'account_type' => AccountTypeEnum::class,
    ];

    /**
     * Get the segment definition this value belongs to
     */
    public function segmentDefinition()
    {
        return $this->belongsTo(AccountSegment::class, 'segment_definition_id');
    }

    /**
     * Get all account assignments for this segment value
     */
    public function accountAssignments()
    {
        return $this->hasMany(AccountSegmentAssignment::class, 'segment_value_id');
    }

    /**
     * Get all accounts that use this segment value
     */
    public function accounts()
    {
        return $this->belongsToMany(GlAccount::class, 'fin_account_segment_assignments', 'segment_value_id', 'account_id');
    }

    public function getDisplayAttribute()
    {
        return $this->segment_value . ' - ' . $this->segment_description;
    }

    /**
     * Scope for active values
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForLastSegment($query)
    {
        return $query->whereHas('segmentDefinition', function ($q) {
            $q->where('segment_position', AccountSegmentService::getLastSegmentPosition());
        });
    }

    /**
     * Scope for specific segment position
     */
    public function scopeForPosition($query, int $position)
    {
        return $query->whereHas('segmentDefinition', function ($q) use ($position) {
            $q->where('segment_position', $position);
        });
    }

    /**
     * Get segment values for a specific position
     */
    public static function getForPosition(int $position, bool $activeOnly = true): \Illuminate\Support\Collection
    {
        $query = static::forPosition($position);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('segment_value')->get();
    }

    /**
     * Get options for dropdown (value => description)
     */
    public static function getOptionsForPosition(int $position): \Illuminate\Support\Collection
    {
        return static::getForPosition($position)
            ->pluck('segment_description', 'segment_value');
    }

    /**
     * Find segment value by position and value
     */
    public static function findByPositionAndValue(int $position, string $value): ?self
    {
        return static::forPosition($position)
            ->where('segment_value', $value)
            ->first();
    }

    /**
     * Validate that a segment value exists for a position
     */
    public static function validateValue(int $position, string $value): bool
    {
        return static::findByPositionAndValue($position, $value) !== null;
    }

    /**
     * Get usage count (how many accounts use this segment value)
     */
    public function getUsageCount(): int
    {
        return $this->accountAssignments()->count();
    }

    /**
     * Check if this segment value can be deleted
     */
    public function canBeDeleted(): bool
    {
        return $this->getUsageCount() === 0;
    }

    public function deletable()
    {
        return true;
    }
}
