<?php

namespace App\Enums\Billing;

/**
 * Why a workspace holds a `PlanGrant`. Only `LifetimePurchase` is reachable
 * through the API today; the others exist so a comped or promotional grant
 * is a row rather than a new code path.
 */
enum PlanGrantSource: string
{
    case LifetimePurchase = 'lifetime_purchase';
    case Manual = 'manual';
    case Promo = 'promo';
}
