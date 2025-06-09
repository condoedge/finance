<?php

namespace Condoedge\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Condoedge\Utils\Models\Model;

/**
 * GL Account Segment Type Model
 * 
 * Represents the different types of GL account segments.
 * This model is linked to the GlAccountSegmentTypeEnum for consistent type definitions.
 */
class GlAccountSegmentType extends Model
{
    use HasFactory;

    protected $table = 'fin_gl_account_segment_types';

    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * Get the enum instance for this segment type
     * 
     * @return GlAccountSegmentTypeEnum
     */
    public function getEnum(): GlAccountSegmentTypeEnum
    {
        return GlAccountSegmentTypeEnum::from($this->id);
    }
}
