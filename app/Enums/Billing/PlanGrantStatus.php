<?php

namespace App\Enums\Billing;

enum PlanGrantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
}
