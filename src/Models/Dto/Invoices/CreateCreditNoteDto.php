<?php

namespace Condoedge\Finance\Models\Dto\Invoices;

use Carbon\Carbon;
use Condoedge\Finance\Facades\InvoiceModel;
use Illuminate\Validation\Validator;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\CarbonCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create a credit note.
 *
 * Line amounts are stated POSITIVE here — "credit the member $120" — and the service
 * negates them on the way in. This is the credit-note API; the generic CreateInvoiceDto
 * still takes signed amounts, because a negative line on a real invoice is a rebate.
 *
 * With `credited_invoice_id` and no `lines`, the source invoice's lines are cloned in
 * full, keeping their revenue accounts and taxes so the tax reversal is exact.
 *
 * @property int|null $credited_invoice_id The invoice this note corrects
 * @property int|null $customer_id Required only for a standalone credit note
 * @property bool|null $apply_to_invoice Apply it to the source invoice straight away
 * @property array|null $lines Positive-amount lines; omit to clone the source invoice
 */
class CreateCreditNoteDto extends ValidatedDTO
{
    public ?int $credited_invoice_id;
    public ?int $customer_id;
    public Carbon $invoice_date;
    public ?bool $apply_to_invoice;
    public ?array $lines;
    public ?int $team_id;

    public function rules(): array
    {
        return [
            'credited_invoice_id' => 'nullable|integer|exists:fin_invoices,id',
            'customer_id' => 'required_without:credited_invoice_id|nullable|integer|exists:fin_customers,id',
            'invoice_date' => 'required|date',
            'apply_to_invoice' => 'nullable|boolean',
            'team_id' => 'nullable|integer|exists:teams,id',

            'lines' => 'nullable|array',
            'lines.*.name' => 'required|string|max:255',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.quantity' => 'required|integer|min:1',
            // Signed, not positive-only: a rebate line reverses as a positive one.
            'lines.*.unit_price' => 'required|numeric',
            'lines.*.revenue_account_id' => 'required|integer|exists:fin_gl_accounts,id',
            'lines.*.taxesIds' => 'nullable|array',
            'lines.*.taxesIds.*' => 'integer|exists:fin_taxes,id',
        ];
    }

    public function casts(): array
    {
        return [
            'credited_invoice_id' => new IntegerCast(),
            'customer_id' => new IntegerCast(),
            'team_id' => new IntegerCast(),
            'invoice_date' => new CarbonCast(),
            'apply_to_invoice' => new BooleanCast(),
            'lines' => new ArrayCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'apply_to_invoice' => false,
        ];
    }

    public function after(Validator $validator): void
    {
        $sourceId = $this->dtoData['credited_invoice_id'] ?? null;

        if (!$sourceId) {
            return;
        }

        $source = InvoiceModel::find($sourceId);

        if (!$source) {
            return;
        }

        if ($source->isRefund()) {
            $validator->errors()->add('credited_invoice_id', __('finance-cannot-credit-a-credit-note'));

            return;
        }

        if ($source->is_draft) {
            $validator->errors()->add('credited_invoice_id', __('finance-cannot-credit-a-draft-invoice'));

            return;
        }

        $lines = $this->dtoData['lines'] ?? [];

        if (!$lines) {
            return;
        }

        // Signed lines net out, so a credit that credits nothing is a mistake, not a no-op.
        // The amount is checked against the invoice in the service, where taxes are known.
        $total = collect($lines)->sum(fn ($line) => ($line['quantity'] ?? 0) * ($line['unit_price'] ?? 0));

        if ($total <= 0) {
            $validator->errors()->add('lines', __('finance-credit-total-must-be-positive'));
        }
    }
}
