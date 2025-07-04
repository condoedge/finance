<?php

namespace Condoedge\Finance\Facades;

use Condoedge\Utils\Facades\FacadeEnum;

/**
 * @mixin \Condoedge\Finance\Enums\SegmentDefaultHandlerEnum
 */
class SegmentDefaultHandlerEnum extends FacadeEnum
{
    protected static function getFacadeAccessor()
    {
        return SEGMENT_DEFAULT_HANDLER_ENUM_KEY;
    }
}
