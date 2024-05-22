<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Condoedge\Finance\Models\Invoice;
use Kompo\Table;
use Illuminate\Support\Carbon;
use Kompo\Elements\Element;

class InvoicesAdminTable extends InvoicesTable
{
    public function created()
    {
        auth()->user()->switchUnion(Union::find(env('FINANCE_ADMIN_UNION_ID')));

        parent::created();
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _PageTitle('finance.condoedge-clients')->class('mb-4'),
            )
        );
    }
}
