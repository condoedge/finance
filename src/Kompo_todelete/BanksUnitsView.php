<?php

namespace Condoedge\Finance\Kompo;

use Kompo\Table;

class BanksUnitsView extends Table
{
    protected $bankUnitsPanel = 'bank-units-panel';

    protected $unitsWithoutBank;

    public function created()
    {
        $this->unitsWithoutBank = auth()->user()->ownedUnitsWithoutBank()->count();
    }

    public function query()
    {
        return auth()->user()->currentContactUnits()
            ->filter(fn($cu) => $cu->isOwner())
            ->flatMap(fn($cu) => $cu->unit->banks);
    }

    public static function unitWarningMessage($unitsWithoutBank, $linkToCreate)
    {
        return _WarningMessage(
            _Html(__('finance.you-have').' '.$unitsWithoutBank.' '.__('finance.unit-not-configured-text')),
            $linkToCreate,
        );
    }

    public function top()
    {
        return _Rows(
            _PageTitle('finance.contribution-payments-configuration')
                ->class('mb-4'),

            $this->unitsWithoutBank ?

                static::unitWarningMessage(
                    $this->unitsWithoutBank,
                    _Link('finance.add-new-bank-account')->class('underline')
                        ->get('bank-units.form')
                        ->inPanel($this->bankUnitsPanel),
                ) :

                _Rows(
                    _Html('finance.unit-correctly-configured'),
                )->class('bg-positive text-positive bg-opacity-15 p-4 rounded-lg shadow')
                ->icon(
                    _Svg('check-circle')->class('text-3xl mr-4')
                ),
        )->class('mb-6');
    }

    public function right()
    {
        return _Rows(
            _Panel(
                _DashedBox('finance.view-bank-detail')->class('p-4')

            )->id($this->bankUnitsPanel)
        )->class('dashboard-card p-4 w-1/3vw');
    }

    public function headers()
    {
        return [
            _Th('general.name'),
            _Th('Units'),
            _Th(),
        ];
    }

    public function render($bank)
    {
    	return _TableRow(
            _Html($bank->display),
            _Rows(
                $bank->units->map(fn($unit) => _Html($unit->name))
            ),
            _DeleteLink()->byKey($bank)
        )->class('cursor-pointer')->get('bank-units.form', [
            'id' => $bank->id
        ])->inPanel($this->bankUnitsPanel);
    }
}
