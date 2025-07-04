<?php

namespace Condoedge\Finance\Http\Controllers\Api;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Condoedge\Finance\Models\SegmentValue;

class AccountSegmentController extends ApiController
{
    /**
     * @operationId Get segment structure
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
     * @operationId Create segment definition
     */
    public function saveSegment(CreateOrUpdateSegmentDto $data)
    {
        $segment = AccountSegmentService::createOrUpdateSegment($data);

        return $this->success($segment, 'Segment created successfully', 201);
    }

    /**
     * @operationId Delete segment definition
     */
    public function deleteSegment($segmentId)
    {
        AccountSegmentService::deleteSegment($segmentId);
    }

    /**
     * @operationId Get natural accounts
     */
    public function getNaturalAccountsValues()
    {
        $values = SegmentValue::ForLastSegment()->get();

        return $this->success([
            'values' => $values,
        ]);
    }

    /**
     * @operationId Create natural account
     */
    public function createNaturalAccountValue(CreateSegmentValueDto $data)
    {
        $value = AccountSegmentService::createSegmentValue($data);

        return $this->success($value, 'Segment value created successfully', 201);
    }

    /**
     * @operationId Delete natural account
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
