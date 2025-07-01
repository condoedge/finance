<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use WendellAdriel\ValidatedDTO\Concerns\EmptyCasts;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * DTO for creating GL Transaction Headers
 */
class CreateGlTransactionHeaderDto extends ValidatedDTO
{
    use EmptyDefaults, EmptyCasts;

    public ?int $team_id = null;
    public ?string $fiscal_date = null;
    public ?int $gl_transaction_type = null; // 1=Manual GL, 2=AR, 3=AP, 4=BNK
    public ?string $transaction_description = null;
    public ?int $customer_id = null;
    public ?int $vendor_id = null;
    public array $transaction_lines = []; // Array of CreateGlTransactionLineDto
    
    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'team_id' => 'required|integer|exists:teams,id',
            'fiscal_date' => 'required|date',
            'gl_transaction_type' => 'required|integer|in:' . collect(GlTransactionTypeEnum::cases())->pluck('value')->implode(','),
            'transaction_description' => 'required|string|max:500',
            'customer_id' => 'nullable|integer|exists:fin_customers,id',
            'vendor_id' => 'nullable|integer',
            'transaction_lines' => 'required|array|min:2',
        ];
    }
}
