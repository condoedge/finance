<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Kompo\Form;

class OtonomUnionPpaKeyForm extends Form
{
	public $model = Union::class;

	protected $refresh = true;

	public function render()
	{
		return [
			_Input('finance.enter-otonom-ppa-key')
				->name('ppa')
				->submit(),
			$this->model->ppa ?
				_Html('finance.ppa-key-saved')->icon('icon-check')
					->class('p-4 bg-positive bg-opacity-50 mb-4 rounded-lg text-sm') :
				_Html('finance.if-no-ppa-key')
					->class('mb-2 text-sm font-semibold text-greenmain'),
			_Rows(
				_Link('https://www.otonomsolution.com/')->class('underline')
					->href('https://www.otonomsolution.com/')->inNewTab(),
				_Html('info@otonomsolution.com '),
				_Html('514 360-6661 / 1 855 OTONOM 1')
			)->class('border border-gray-200 p-4 rounded-lg'),
		];
	}
}
