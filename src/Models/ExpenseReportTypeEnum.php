<?php

namespace Condoedge\Finance\Models;

enum ExpenseReportTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case GENERAL = 1;
    case TRAVEL = 2;
    case MEAL = 3;
    case EQUIPMENT = 4;
    case CAMPING = 5;
    case TRAINING = 6;
    case ACTIVITY = 7;
    case UNIFORM = 8;
    case COMMUNICATION = 9;
    case SUPPLIES = 10;
    case OTHER = 99;

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => __('finance-general'),
            self::TRAVEL => __('finance-travel'),
            self::MEAL => __('finance-meal'),
            self::EQUIPMENT => __('finance-equipment'),           // Matériel permanent (cordes, tentes, etc.)
            self::CAMPING => __('finance-camp-expense'),               // Frais de camp (terrain, bois, etc.)
            self::TRAINING => __('finance-training'),             // Formations (ex. premiers soins, animation)
            self::ACTIVITY => __('finance-activity'),             // Activités ponctuelles (ex. sortie au musée)
            self::UNIFORM => __('finance-uniform'),               // Uniformes, badges, écussons
            self::COMMUNICATION => __('finance-communication'),   // Timbres, pub, impression, site web
            self::SUPPLIES => __('finance-supplies'),             // Fournitures variées (papier, crayons, etc.)
            self::OTHER => __('finance-other'),
        };
    }
}
