<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
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
        $dto = new CreateOrUpdateSegmentDto([
            'segment_description' => $request->input('segment_description'),
            'segment_position' =>  $request->input('segment_position'),
            'segment_length' =>  $request->input('segment_length'),
            'is_active' => true,
        ]);
        
        $segment = AccountSegmentService::createOrUpdateSegment($dto);
        
        return $this->success($segment, 'Segment created successfully', 201);
    }
    
    /**
     * Delete segment definition
     */
    public function deleteSegment($segmentId)
    {
        AccountSegmentService::deleteSegment($segmentId);
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
        $dto = new CreateSegmentValueDto([
            'segment_definition_id' => $request->input('segment_definition_id'),
            'segment_value' => $request->input('segment_value'),
            'segment_description' => $request->input('segment_description'),
            'is_active' => $request->input('is_active') ?? true,
        ]);
        
        $value = AccountSegmentService::createSegmentValue($dto);
        
        return $this->success($value, 'Segment value created successfully', 201);
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
            if (isset($validated['segment_description'])) {
                $value->segment_description = $validated['segment_description'];
            }
            if (isset($validated['is_active'])) {
                $value->is_active = $validated['is_active'];
            }
            $value->save();
            
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
            'segment_definition_id' => 'required|integer|exists:fin_account_segments,id',
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
                    $dto = new CreateSegmentValueDto([
                        'segment_definition_id' => $validated['segment_definition_id'],
                        'segment_value' => $item['value'],
                        'segment_description' => $item['description'],
                        'is_active' => true,
                    ]);
                    
                    $value = AccountSegmentService::createSegmentValue($dto);
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
