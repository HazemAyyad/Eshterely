<?php

namespace App\Enums\Payment;

enum PaymentEventSource: string
{
    case System = 'system';
    case Webhook = 'webhook';
    case Admin = 'admin';
}
