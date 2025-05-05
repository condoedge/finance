<?php

namespace Condoedge\Finance\Models\Dto;

/**
 * @property integer $applicable_id The ID of the applicable record.
 * @property integer $applicable_type The type of the applicable record as a MorphableEnum value (e.g., invoice = 1, credit = 2).
 * @property float $applicable_amount_left The remaining amount that can be applied.
 * @property string $applicable_name A human-readable name or description of the applicable record.
 */
class ApplicableRecordDto
{
    public int $applicable_id;
    public string $applicable_type;
    
    public float $applicable_amount_left;
    public string $applicable_name;
}