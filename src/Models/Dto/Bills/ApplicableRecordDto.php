<?php

namespace Condoedge\Finance\Models\Dto\Bills;

class ApplicableRecordDto
{
    public float $applicable_amount_left;
    public string $applicable_name;
    public int $applicable_id;
    public int $applicable_type;

    public function __construct(
        float $applicable_amount_left,
        string $applicable_name,
        int $applicable_id,
        int $applicable_type
    ) {
        $this->applicable_amount_left = $applicable_amount_left;
        $this->applicable_name = $applicable_name;
        $this->applicable_id = $applicable_id;
        $this->applicable_type = $applicable_type;
    }
}
