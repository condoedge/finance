<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;

class ChargeDetail extends Model
{
    use \Condoedge\Finance\Models\MorphToManyTaxesTrait;
    use \Condoedge\Finance\Models\BelongsToInvoiceTrait;
    use \Condoedge\Finance\Models\BelongsToGlAccountTrait;

    /* RELATIONSHIPS */
    public function chargeable()
    {
        return $this->morphTo();
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */
    public function getAmountForTax($taxId)
    {
        //TODO REVIEW
        return ($this->taxes()->where('taxes.id', $taxId)->value('rate') ?: 0) * $this->amount;
    }

    /* ACTIONS */
    public function calculateAmountsChd()
    {
        $this->pretax_amount_chd = round($this->quantity_chd * $this->price_chd, 2);

        $this->loadMissing('taxes');
        $this->tax_amount_chd = round($this->taxes->sum('rate') * $this->amount_chd, 2);

        $this->total_amount_chd = $this->pretax_amount_chd + $this->tax_amount_chd;

        $this->save();
    }

    public function reduceFromStockInventory($union, $usedDate, $transactionSource = null)
    {
        if ($this->chargeable instanceOf \App\Models\Market\Product) {
            $this->chargeable->reduceFifoStockInventory($union, $usedDate, $this->quantity_chd, $transactionSource);
        }
    }

    public function addToStockInventory($union, $usedDate, $transactionSource = null)
    {
        if ($this->chargeable instanceOf \App\Models\Market\Product) {
            $this->chargeable->createNewStockInventory($union, $usedDate, $this->quantity_chd, $this->price_chd, $transactionSource);
        }
    }

    public function delete()
    {
        $this->taxes()->sync([]);

        parent::delete();
    }

    /* ELEMENTS */
    public function getChargeableHiddenEls($chargeable)
    {
        return _Rows(
            _Hidden()->name('chargeable_id')->value($chargeable?->id ?: $this->chargeable_id),
            _Hidden()->name('chargeable_type')->value($chargeable ? $chargeable::getRelationType() : $this->chargeable_type),
        );
    }

}
