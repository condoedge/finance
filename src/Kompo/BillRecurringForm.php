<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bill;
use App\Models\Recurrence;

class BillRecurringForm extends BillForm
{
	protected $recurrenceId;

	public function created()
	{
		parent::created();

		$this->recurrenceId = $this->model->recurrence_id;
		$this->recurrence = Recurrence::find($this->recurrenceId) ?: new Recurrence();

		if ($this->model->id && $this->recurrence->id) {
			if ($this->recurrence->recu_start < $this->model->billed_at) {
				$this->recurrence->recu_start = $this->model->billed_at;
				$this->recurrence->save();
			}
		}
	}

	public function beforeSave()
	{
		parent::beforeSave();

		if (!$this->recurrence->id) {
			$this->union->checkIfDateAcceptable(request('recu_start'));
		}

		if (!$this->recurrence->id) {
			$this->recurrence->setTeamId();
			$this->recurrence->setUnionId();
			$this->recurrence->setUserId();
			$this->recurrence->child_type = Recurrence::CHILD_BILL;
		}

		$this->recurrence->recu_period = request('recu_period');
		$this->recurrence->recu_start = request('recu_start');
		$this->recurrence->recu_end = request('recu_end');
		$this->recurrence->save();

		//First bill columns
		$this->model->recurrence_id = $this->recurrence->id;
		$this->model->billed_at = $this->recurrence->recu_start;
	}

	public function completed()
	{
		parent::completed();

		$this->recurrence->checkAndCreateBills();
	}

	public function response()
	{
		return redirect()->route('bills-recurring.table');
	}

	protected function getRecurrenceFields()
	{
		return _Rows(
			_TitleMini('finance.recurrence')->class('mb-2'),
			$this->sectionBox(
				_Flex4(
					_Html('finance.repeat-this-bill')->class('mb-4'),
					_Select()->placeholder('finance.every-three-dots')->name('recu_period', false)
						->options(Recurrence::recurrences())->value($this->recurrence->recu_period),
				),
				_Flex4(
					_Html('finance.create-the-first-bill-on-the')->class('mb-4'),
					_Date()->name('recu_start', false)->value($this->recurrence->recu_start),
					_Html('finance.and-finish-on-the')->class('mb-4'),
					_Date()->name('recu_end', false)->value($this->recurrence->recu_end),
				),
			)->class('shadow-lg'),
		);
	}

	protected function titleBack()
	{
		return _Link('finance.recurring-bills')->href('bills-recurring.table');
	}

	protected function deleteBillButtons()
	{
		if (!$this->recurrence->id) {
			return;
		}

		return _Dropdown('Actions')->submenu(
			_DropdownLink('Disable')->selfPost('disableRecurrence')->alert('finance.recurrence-disabled'),
			_DropdownLink('finance.delete-with-all-its-bills')->selfPost('deleteRecurrence')->alert('finance.recurrence-and-bills-deleted'),
		)->alignRight();
	}

	public function disableRecurrence()
	{
		$this->recurrence->recu_end = carbon(date('Y-m-d'))->addDays(-1);
		$this->recurrence->save();

		return redirect()->route('bills-recurring.table');
	}

	public function deleteRecurrence()
	{
		$this->recurrence->bills->each(function($bill){
			if (auth()->user()->can('delete', $bill)) {
	            $bill->delete();
	        } else {
	        	$bill->recurrence_id = null;
	        	$bill->save();
	        }
		});

		$this->recurrence->delete();

		return redirect()->route('bills-recurring.table');
	}

	protected function getBillDateEl()
	{
		return _Input('finance.bill-date')->name('billed_at', false)->readOnly()->icon(_SaxSvg('calendar'))
			->default($this->recurrence->recu_start?->format('Y-m-d') ?: __('finance.equals-recurrence-start-date'));
	}

	protected function getWorkDateEl()
	{
		return _Html();
	}

	public function rules()
	{
		return array_merge(parent::rules(), [
			'recu_period' => 'required',
			'recu_start' => 'required',
			'recu_end' => 'required',
		]);
	}
}
