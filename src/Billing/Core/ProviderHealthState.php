<?php

namespace Condoedge\Finance\Billing\Core;

enum ProviderHealthState: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case DOWN = 'down';
}