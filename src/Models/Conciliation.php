<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Entry;
use App\Models\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conciliation extends Model
{
    use SoftDeletes;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /* RELATIONSHIPS */
    public function account()
    {
        return $this->belongsTo(GlAccount::class);
    }

    /* ACTIONS */
    public function calculateAmountsFromEntries()
    {
        $this->resolved = !$this->reconciled_ids ? 0 :
            Entry::whereIn('id', explode(',', $this->reconciled_ids))->selectRaw('SUM(debit - credit) as amount')->value('amount');

        $this->remaining = $this->closing_balance - $this->opening_balance - $this->resolved;

        $this->save();
    }

    public function syncEntryToReconciled($entryId, $addSide = true)
    {
        $entry = Entry::findOrFail($entryId);
        $entry->reconciled_during = $addSide ? $this->end_date : null; //Has to be null when not reconciled!
        $entry->save();

        $reconciledIds = $this->reconciled_ids ? explode(',', $this->reconciled_ids) : [];

        if ($addSide) {
            $reconciledIds = array_merge($reconciledIds, [$entryId]);
        }else{
            $reconciledIds = array_diff($reconciledIds, [$entryId]);
        }

        $this->reconciled_ids = implode(',', $reconciledIds);
        $this->save();
        $this->calculateAmountsFromEntries();
    }

    /* ATTRIBUTES */

    /* CALCULATED FIELDS */
    

    /* SCOPES */
}
