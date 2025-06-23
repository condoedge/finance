<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;
use Illuminate\Support\Facades\DB;

/**
 * Account Segment Assignment Model
 * 
 * Pivot table that creates accounts by assigning segment values.
 * Account ID and descriptor are computed by database functions.
 * 
 * @property int $id
 * @property int $account_id References fin_gl_accounts
 * @property int $segment_value_id References fin_segment_values
 */
class AccountSegmentAssignment extends Model
{
    protected $table = 'fin_account_segment_assignments';
    
    protected $fillable = [
        'account_id',
        'segment_value_id',
    ];
    
    protected $casts = [
        'account_id' => 'integer',
        'segment_value_id' => 'integer',
    ];
    
    /**
     * Get the account this assignment belongs to
     */
    public function account()
    {
        return $this->belongsTo(GlAccount::class, 'account_id');
    }
    
    /**
     * Get the segment value for this assignment
     */
    public function segmentValue()
    {
        return $this->belongsTo(SegmentValue::class, 'segment_value_id');
    }
    
    /**
     * Get assignments for a specific account ordered by segment position
     */
    public static function getForAccount(int $accountId): \Illuminate\Support\Collection
    {
        return static::where('account_id', $accountId)
            ->with(['segmentValue.segmentDefinition'])
            ->get()
            ->sortBy(function ($assignment) {
                return $assignment->segmentValue->segmentDefinition->segment_position;
            });
    }
    
    /**
     * Create assignments for an account from segment value IDs
     * 
     * @param int $accountId
     * @param array $segmentValueIds Array of segment_value_ids
     */
    public static function createForAccount(int $accountId, array $segmentValueIds): void
    {
        DB::transaction(function () use ($accountId, $segmentValueIds) {
            // Clear existing assignments
            static::where('id', $accountId)->delete();
            
            // Create new assignments
            foreach ($segmentValueIds as $segmentValueId) {
                $assigment = new static();
                $assigment->account_id = $accountId;
                $assigment->segment_value_id = $segmentValueId;
                $assigment->save();
            }
        });
    }
    
    /**
     * Validate segment value combination has all required segments
     * 
     * @param array $segmentValueIds Array of segment value IDs
     * @return bool
     */
    public static function validateSegmentValueCombination(array $segmentValueIds): bool
    {
        // Get the segment definitions for provided values
        $providedSegments = SegmentValue::whereIn('id', $segmentValueIds)
            ->with('segmentDefinition')
            ->get()
            ->pluck('segmentDefinition.segment_position')
            ->unique();
        
        // Get all active segment positions
        $requiredPositions = AccountSegment::where('is_active', true)
            ->pluck('segment_position');
        
        // Check all required positions are covered
        return $requiredPositions->diff($providedSegments)->isEmpty();
    }
    
    /**
     * Find accounts by exact segment value combination
     * 
     * @param array $segmentValueIds
     * @param int $teamId
     * @return \Illuminate\Support\Collection
     */
    public static function findAccountsBySegmentValues(array $segmentValueIds): \Illuminate\Support\Collection
    {
        $accountIds = DB::table('fin_account_segment_assignments')
            ->select('account_id')
            ->whereIn('segment_value_id', $segmentValueIds)
            ->groupBy('account_id')
            ->havingRaw('COUNT(DISTINCT segment_value_id) = ?', [count($segmentValueIds)])
            ->pluck('account_id');
        
        if ($accountIds->isEmpty()) {
            return collect();
        }
        
        // Verify accounts have ONLY these segments
        $validAccountIds = [];
        foreach ($accountIds as $accountId) {
            $count = static::where('account_id', $accountId)->count();
            if ($count === count($segmentValueIds)) {
                $validAccountIds[] = $accountId;
            }
        }
        
        return GlAccount::whereIn('id', $validAccountIds)
            ->where('team_id', $teamId)
            ->get();
    }
    
    /**
     * Get all accounts using a specific segment value
     */
    public static function getAccountsUsingSegmentValue(int $segmentValueId): \Illuminate\Support\Collection
    {
        return static::where('segment_value_id', $segmentValueId)
            ->with('account')
            ->get()
            ->pluck('account')
            ->unique('id');
    }
    
    /**
     * Get segment value for specific position in an account
     * Uses database function for consistency
     */
    public static function getAccountSegmentValueAtPosition(int $accountId, int $position): ?string
    {
        $result = DB::selectOne(
            'SELECT get_account_segment_value(?, ?) as segment_value',
            [$accountId, $position]
        );
        
        return $result ? $result->segment_value : null;
    }
    
    /**
     * Validate account has all required segments
     * Uses database function
     */
    public static function validateAccountCompleteness(int $accountId): bool
    {
        $result = DB::selectOne(
            'SELECT validate_account_segments(?) as is_valid',
            [$accountId]
        );
        
        return $result && $result->is_valid;
    }
}
