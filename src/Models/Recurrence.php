<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Model;
use Condoedge\Finance\Models\Bill;

class Recurrence extends Model
{
    use \Kompo\Auth\Models\Traits\BelongsToUserTrait;
    use \Kompo\Auth\Models\Teams\BelongsToTeamTrait;

    public const CHILD_BILL = 1;

    public const RECU_DAY = 2;
    public const RECU_WEEK = 5;
    public const RECU_2WEEKS = 7;
    public const RECU_MONTH = 10;
    public const RECU_6WEEKS = 12;
    public const RECU_2MONTHS = 15;
    public const RECU_3MONTHS = 17;
    public const RECU_6MONTHS = 20;
    public const RECU_YEAR = 27;

    protected $casts = [
        'recu_start' => 'date',
        'recu_end' => 'date',
    ];

    /* RELATIONS */
    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    /* ATTRIBUTES */
    public function getScheduleLabelAttribute()
    {
        return __('Repeats').' '.static::recurrences()[$this->recu_period];
    }

    public function getScheduleFrameAttribute()
    {
        return __('Starts').': '.$this->recu_start->translatedFormat('d M Y').' | '.__('Ends').': '.$this->recu_end->translatedFormat('d M Y');
    }


    /* CALCULATED FIELD */
    public function getMainBill()
    {
        return $this->bills()->where('billed_at', '>=', $this->union->latestBalanceDate())->first();
    }

    public function getNextBill()
    {
        return $this->bills()->where('billed_at', '>', date('Y-m-d'))->first();
    }

    public function shouldCreateForDate($date)
    {
        return ($date <= $this->recu_end) || ($date < date('Y-m-d'));
    }

    public static function recurrences()
    {
        return [
            static::RECU_DAY => __('daily'),
            static::RECU_WEEK => __('weekly'),
            static::RECU_2WEEKS => __('every-2-weeks'),
            static::RECU_MONTH => __('monthly'),
            static::RECU_6WEEKS => __('every-6-weeks'),
            static::RECU_2MONTHS => __('every-2-months'),
            static::RECU_3MONTHS => __('every-3-months'),
            static::RECU_6MONTHS => __('every-6-months'),
            static::RECU_YEAR => __('yearly'),
        ];
    }

    public static function recurrenceCalculations()
    {
        return [
            static::RECU_DAY => fn($date) => $date->addDays(1),
            static::RECU_WEEK => fn($date) => $date->addDays(7),
            static::RECU_2WEEKS => fn($date) => $date->addDays(14),
            static::RECU_MONTH => fn($date) => $date->addMonths(1),
            static::RECU_6WEEKS => fn($date) => $date->addDays(42),
            static::RECU_2MONTHS => fn($date) => $date->addMonths(2),
            static::RECU_3MONTHS => fn($date) => $date->addMonths(3),
            static::RECU_6MONTHS => fn($date) => $date->addMonths(6),
            static::RECU_YEAR => fn($date) => $date->addYear(1),
        ];
    }


    /* ACTIONS */
    public function checkAndCreateBills()
    {
        $mainBill = $this->getMainBill();

        $nextDate = $this->getNextDateAfter($mainBill->billed_at);

        //Create past bills if needed
        while ($this->shouldCreateForDate($nextDate)) {

            $this->createBillForDate($nextDate);

            $nextDate = $this->getNextDateAfter($nextDate);
        }

        //Create the next bill if needed
        if ($this->shouldCreateForDate($nextDate)) {

            $this->createBillForDate($nextDate);

        }
    }

    public function getNextDateAfter($date)
    {
        $recuCalc = static::recurrenceCalculations()[$this->recu_period];

        return $recuCalc(carbon($date));
    }

    public function createBillForDate($date)
    {
        if ($bill = $this->bills()->where('billed_at', $date)->first()) {
            return;
        }

        $mainBill = $this->getMainBill();
        $newBill = $mainBill->replicate();
        $newBill->billed_at = $date;
        $newBill->due_at = $date;
        $newBill->bill_number = Bill::getBillIncrement($newBill->union_id, Bill::PREFIX_BILL);
        $newBill->save();

        foreach ($mainBill->chargeDetails as $chargeDetail) {

            $newChargeDetail = $chargeDetail->replicate();
            $newChargeDetail->bill_id = $newBill->id;
            $newChargeDetail->save();

            $newChargeDetail->taxes()->sync($chargeDetail->taxes->pluck('id'));
        }

        $newBill->createJournalEntries();
    }


    /* ELEMENTS */
    public function getStatusPill()
    {
        $pill = _Pill()->class('text-white text-xs');

        if ($this->recu_start->format('Y-m-d') > date('Y-m-d')) {
            $pill = $pill->label('Awaiting')->class('bg-info');
        } else if ($this->recu_end->format('Y-m-d') < date('Y-m-d')) {
            $pill = $pill->label('Expired')->class('bg-danger');
        } else {
            $pill = $pill->label('Active')->class('bg-positive');
        }

        return $pill;
    }

}
