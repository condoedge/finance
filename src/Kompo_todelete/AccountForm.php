<?php

namespace Condoedge\Finance\Kompo;

use App\Kompo\Common\Modal;
use Condoedge\Finance\Models\GlAccount;
use Illuminate\Validation\ValidationException;

class AccountForm extends Modal
{
	public $model = GlAccount::class;
    protected $noHeaderButtons = true;

    protected $subCodeId;
    protected $lastSibling;

    public function created()
    {
        $this->subCodeId = $this->prop('sub_code_id');

  		$this->_Title = $this->model->id ? 'finance.edit-account-number' : 'finance.add-new-account';
    	$this->lastSibling = GlAccount::getLastSibling($this->subCodeId, $this->model->union_id);
    }

    public function beforeSave()
    {
    	if ($this->model->isDuplicateCode(request('code'))) {

    		$possibleCode = $this->subCodeId * 100;

    		for ($i = 0; $i < 100; $i++) {
    			$possibleCode++;
    			if (!$this->model->isDuplicateCode($possibleCode)) {
    				throw ValidationException::withMessages([
		               'code' => [
		                    __('finance.code-already-used').'. '.__('Suggestion').': '.$possibleCode
		                ],
		            ]);
    			}
    		}
    	}

    	if (!$this->model->id) {

    		$this->model->setUnionId();
	        $this->model->level = GlAccount::LEVEL_MEDIUM;
	        $this->model->group = $this->lastSibling->group;
	        $this->model->type = $this->lastSibling->getTranslations('type');
	        $this->model->code = GlAccount::getNextCode($this->lastSibling);
	        $this->model->fund_id = request('fund_id');
    	}
    }

	public function body()
	{
		if ($this->model->id && !$this->model->name) {
			$this->model->name = $this->model->getTranslations('type');
		}
		
		return [
			_Translatable('Name')->name('name'),
			_Translatable('SubName')->name('subname'),
			_Select('finance.linked-fund')->name('fund_id')->options(currentUnion()->funds()->pluck('name', 'id')),
			_Translatable('Description')->name('description')->asTextarea(),
			!$this->model->bank_id ? null :
				_Rows(
					_Html('finance.bank-info')->class('vlFormLabel'),
					_Link($this->model->display)->icon(_Sax('bank'))
						->class('p-4 border border-level3 border-opacity-75 rounded-lg mb-4')
						->get('banks.form', ['id' => $this->model->bank_id])->inModal(),
				),
			_FlexBetween(
				_Toggle('finance.enabled')->name('enabled')->default(1),
				_InputNumber()->name('code')->icon('<span class="text-gray-300">code:</span>')->class('flex-1 ml-4')->inputClass('text-right')
					->default(GlAccount::getNextCode($this->lastSibling)),
			),
			_SubmitButton('general.save'),
		];
	}

	public function rules()
	{
		return [
			'name' => 'required',
			'code' => 'required|numeric|between:'.$this->subCodeId.'00,'.$this->subCodeId.'99',
		];
	}
}
