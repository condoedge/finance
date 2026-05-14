<?php

namespace Condoedge\Finance\Exceptions;

use Exception;

class SegmentValueOverflowException extends Exception
{
    public function __construct(
        public readonly int $segmentId,
        public readonly string $segmentDescription,
        public readonly int $segmentLength,
        public readonly string $value,
        public readonly ?int $teamId = null,
    ) {
        parent::__construct(sprintf(
            'Segment "%s" (length %d) cannot hold value "%s" (length %d). '
            . 'Widen the segment definition or the id space exceeded design limits.',
            $segmentDescription,
            $segmentLength,
            $value,
            strlen($value),
        ));
    }

    public function loggingContext(): array
    {
        return [
            'segment_id' => $this->segmentId,
            'segment_description' => $this->segmentDescription,
            'segment_length' => $this->segmentLength,
            'value' => $this->value,
            'team_id' => $this->teamId,
        ];
    }
}
