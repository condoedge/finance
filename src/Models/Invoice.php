<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Events\InvoiceGenerated;
use Illuminate\Support\Facades\DB;

class Invoice extends AbstractMainFinanceModel
{
    protected $table = 'fin_invoices';

    protected $casts = [
        'invoice_date' => 'date',
        'invoice_due_date' => 'date',
    ];

    public function save(array $options = [])
    {
        if ($this->invoice_number === null) {
            $this->setIncrementalNumber(function () use ($options) {
                parent::save($options);
            });

            return;
        }

        return parent::save($options);
    }

    /**
     * Get the creation event class for this model.
     *
     * @return string
     */
    protected function getCreatedEventClass()
    {
        return InvoiceGenerated::class;
    }

    /* RELATIONSHIPS */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /* ATTRIBUTES */
    public function getCustomerLabelAttribute()
    {
        return $this->customer->name;
    }

    public function getInvoiceTypeLabelAttribute()
    {
        if ($this->invoice_type_id === null) {
            return null;
        }

        return InvoiceTypeEnum::from($this->invoice_type_id)?->label();
    }

    public function getPaymentTypeLabelAttribute()
    {
        if ($this->payment_type_id === null) {
            return null;
        }

        return PaymentTypeEnum::from($this->payment_type_id)?->label();
    }

    /* SCOPES */
    public function scopeForTeam($query, $teamId)
    {
        $query->whereHas('customer', function ($query) use ($teamId) {
            $query->where('team_id', $teamId);
        });
    }

    /* CALCULATED FIELDS */
    public function setIncrementalNumber($saveCb)
    {
        return DB::transaction(function () use ($saveCb) {
            $sequence = DB::table('invoice_types')->select('next_number')
                    ->where('id', $this->invoice_type_id)
                    ->lockForUpdate()
                    ->first();
        
            $this->invoice_number = $sequence->next_number;

            // Instead of using $this->save we use the callback to avoid infinite recursion
            $saveCb();
        });
    }

    /* ATTRIBUTES */

    /** INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        DB::table('fin_invoices')
            ->when($ids, function ($query) use ($ids) {
                return $query->whereIn('id', $ids);
            })
            ->update([
                'invoice_due_amount' => DB::raw('calculate_invoice_due(fin_invoices.id)'),
                'invoice_amount' => DB::raw('calculate_invoice_amount(fin_invoices.id)'),
                'invoice_reference' => DB::raw('get_invoice_reference(fin_invoices.id)'),
            ]);
    }
}
