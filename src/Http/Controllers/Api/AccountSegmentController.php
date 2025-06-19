<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountSegmentController extends ApiController
{
    /**
     * Get segment structure
     */
    public function getStructure()
    {
        $segments = AccountSegment::getAllOrdered();
        
        return $this->success([
            'segments' => $segments,
            'format_mask' => AccountSegmentService::getAccountFormatMask(),
        ]);
    }
    
    /**
     * Create segment definition
     */
    public function createSegment(Request $request)
    {
        $validated = $request->validate([
            'segment_description' => 'required|string|max:255',
            'segment_position' => 'required|integer|min:1|max:10',
            'segment_length' => 'required|integer|min:1|max:10',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Check position uniqueness
            if (AccountSegment::where('segment_position', $validated['segment_position'])->exists()) {
                // Reorder positions to make room
                AccountSegment::where('segment_position', '>=', $validated['segment_position'])
                    ->increment('segment_position');
            }
            
            $segment = AccountSegment::create($validated);
            
            // Ensure positions are sequential
            AccountSegment::reorderPositions();
            
            DB::commit();
            
            return $this->success($segment, 'Segment created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update segment definition
     */
    public function updateSegment(Request $request, $segmentId)
    {
        $segment = AccountSegment::findOrFail($segmentId);
        
        $validated = $request->validate([
            'segment_description' => 'sometimes|required|string|max:255',
            'segment_length' => 'sometimes|required|integer|min:1|max:10',
        ]);
        
        try {
            // Prevent length change if segment has values
            if (isset($validated['segment_length']) && 
                $validated['segment_length'] != $segment->segment_length && 
                $segment->hasValues()) {
                throw new \Exception('Cannot change segment length when values exist');
            }
            
            $segment->update($validated);
            
            return $this->success($segment, 'Segment updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete segment definition
     */
    public function deleteSegment($segmentId)
    {
        $segment = AccountSegment::findOrFail($segmentId);
        
        try {
            if ($segment->hasValues()) {
                throw new \Exception('Cannot delete segment with existing values');
            }
            
            DB::beginTransaction();
            
            $segment->delete();
            AccountSegment::reorderPositions();
            
            DB::commit();
            
            return $this->success(null, 'Segment deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get segment values for a position
     */
    public function getValues($position)
    {
        $segment = AccountSegment::getByPosition($position);
        
        if (!$segment) {
            return $this->error('Invalid segment position', 404);
        }
        
        $values = SegmentValue::getForPosition($position, false); // Include inactive
        
        return $this->success([
            'segment' => $segment,
            'values' => $values,
        ]);
    }
    
    /**
     * Create segment value
     */
    public function createValue(Request $request)
    {
        $validated = $request->validate([
            'position' => 'required|integer',
            'segment_value' => 'required|string',
            'segment_description' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);
        
        try {
            $value = AccountSegmentService::createSegmentValue(
                $validated['position'],
                $validated['segment_value'],
                $validated['segment_description']
            );
            
            if (isset($validated['is_active'])) {
                $value->update(['is_active' => $validated['is_active']]);
            }
            
            return $this->success($value, 'Segment value created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update segment value
     */
    public function updateValue(Request $request, $valueId)
    {
        $value = SegmentValue::findOrFail($valueId);
        
        $validated = $request->validate([
            'segment_description' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);
        
        try {
            $value->update($validated);
            
            return $this->success($value, 'Segment value updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete segment value
     */
    public function deleteValue($valueId)
    {
        $value = SegmentValue::findOrFail($valueId);
        
        try {
            if (!$value->canBeDeleted()) {
                throw new \Exception('Cannot delete segment value that is in use');
            }
            
            $value->delete();
            
            return $this->success(null, 'Segment value deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Bulk import segment values
     */
    public function bulkImportValues(Request $request)
    {
        $validated = $request->validate([
            'position' => 'required|integer',
            'values' => 'required|array',
            'values.*.value' => 'required|string',
            'values.*.description' => 'required|string|max:255',
        ]);
        
        $imported = [];
        $errors = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($validated['values'] as $index => $item) {
                try {
                    $value = AccountSegmentService::createSegmentValue(
                        $validated['position'],
                        $item['value'],
                        $item['description']
                    );
                    $imported[] = $value;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'value' => $item['value'],
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            if (empty($imported) && !empty($errors)) {
                DB::rollBack();
                return $this->error('No values could be imported', 400, $errors);
            }
            
            DB::commit();
            
            return $this->success([
                'imported' => $imported,
                'errors' => $errors,
                'summary' => [
                    'total' => count($validated['values']),
                    'imported' => count($imported),
                    'failed' => count($errors),
                ],
            ], 'Bulk import completed');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Validate segment structure
     */
    public function validateStructure()
    {
        $issues = AccountSegmentService::validateSegmentStructure();
        
        if (empty($issues)) {
            return $this->success([
                'valid' => true,
                'issues' => [],
            ], 'Segment structure is valid');
        }
        
        return $this->success([
            'valid' => false,
            'issues' => $issues,
        ], 'Segment structure has issues');
    }
}
