<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Conciliation;
use App\View\Modal;
use Illuminate\Support\Carbon;

class BankReconciliationNewForm extends Modal
{
	protected $_Title = 'finance.new-reconciliation';

	public $model = Conciliation::class;

	public function beforeSave()
	{
        $this->model->start_date = request('recon_period').'-01';
        $this->model->end_date = carbon(request('recon_period').'-01')->addMonths(1)->addDays(-1)->format('Y-m-d');

        $this->model->reconciled_at = now();
	}

	public function response()
	{
        return redirect()->route('conciliation.page', [
            'id' => $this->model->id,
        ]);
	}

	public function headerButtons()
	{
		return _SubmitButton('general.save');
	}

	public function body()
	{
		return [
			_Select('Account')->name('account_id')->options(
                GlAccount::inUnionGl()->cash()->get()->mapWithKeys(fn($account) => $account->getOption())
            ),
			_Select('finance.reconciliation-period')->name('recon_period', false)->class('mb-0')
                ->options(
                    collect(range(0, 72))->mapWithKeys(fn($i) => [
                        Carbon::now()->addMonths(-$i)->format('Y-m') => Carbon::now()->addMonths(-$i)->translatedFormat('F Y'),
                    ])
                ),
		];
	}

	public function rules()
	{
		return [
            'account_id' => 'required',
            'recon_period' => 'required',
        ];
	}
}
