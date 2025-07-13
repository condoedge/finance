<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

class WebhookEvent extends Model
{
    protected $table = 'fin_webhook_events';

    protected $fillable = [
        'event_type',
        'payload',
        'status',
        'external_transaction_ref',
    ];

    public function getPayloadAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setPayloadAttribute($value)
    {
        $this->attributes['payload'] = json_encode($value);
    }    
}