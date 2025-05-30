<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Table;
use Condoedge\Finance\Models\GL\FiscalPeriod;

class FiscalPeriodsTable extends Table
{
    public function query()
    {
        return FiscalPeriod::orderBy('fiscal_year', 'desc')->orderBy('period_number');
    }

    public function headers()
    {
        return [
            _Th('Period ID')->sort('period_id'),
            _Th('Fiscal Year')->sort('fiscal_year'),
            _Th('Period #')->sort('period_number'),
            _Th('Start Date')->sort('start_date'),
            _Th('End Date')->sort('end_date'),
            _Th('GL Status'),
            _Th('BNK Status'),
            _Th('RM Status'),
            _Th('PM Status'),
            _Th('Actions')
        ];
    }

    public function render($period)
    {
        return [
            _Html($period->period_id)->class('font-mono'),
            _Html($period->fiscal_year),
            _Html($period->period_number),
            _Html($period->start_date->format('M d, Y')),
            _Html($period->end_date->format('M d, Y')),
            
            $this->statusBadge($period->is_open_gl),
            $this->statusBadge($period->is_open_bnk),
            $this->statusBadge($period->is_open_rm),
            $this->statusBadge($period->is_open_pm),
            
            _FlexEnd(
                _Button('Edit')
                    ->class('btn-sm btn-outline-primary mr-2')
                    ->onClick(fn() => redirect()->to("fiscal-periods/{$period->period_id}/edit")),
                    
                _Dropdown('Manage')->class('btn-sm btn-outline-secondary')->submenu(
                    _Link('Close GL')->href("fiscal-periods/{$period->period_id}/close/GL")
                        ->if($period->is_open_gl),
                    _Link('Open GL')->href("fiscal-periods/{$period->period_id}/open/GL")
                        ->if(!$period->is_open_gl),
                        
                    _Link('Close BNK')->href("fiscal-periods/{$period->period_id}/close/BNK")
                        ->if($period->is_open_bnk),
                    _Link('Open BNK')->href("fiscal-periods/{$period->period_id}/open/BNK")
                        ->if(!$period->is_open_bnk),
                        
                    _Link('Close RM')->href("fiscal-periods/{$period->period_id}/close/RM")
                        ->if($period->is_open_rm),
                    _Link('Open RM')->href("fiscal-periods/{$period->period_id}/open/RM")
                        ->if(!$period->is_open_rm),
                        
                    _Link('Close PM')->href("fiscal-periods/{$period->period_id}/close/PM")
                        ->if($period->is_open_pm),
                    _Link('Open PM')->href("fiscal-periods/{$period->period_id}/open/PM")
                        ->if(!$period->is_open_pm)
                )
            )
        ];
    }

    protected function statusBadge($isOpen)
    {
        return _Html($isOpen ? 'OPEN' : 'CLOSED')
            ->class($isOpen ? 'badge bg-success' : 'badge bg-danger');
    }

    public function top()
    {
        return [
            _Title('Fiscal Periods')->class('text-xl font-semibold mb-4'),
            
            _FlexBetween(
                _Input('search')
                    ->placeholder('Search periods...')
                    ->icon('fas fa-search')
                    ->class('max-w-xs'),
                    
                _Button('Create New Period')
                    ->class('btn-primary')
                    ->onClick(fn() => redirect()->to('fiscal-periods/create'))
            )->class('mb-4')
        ];
    }
}
