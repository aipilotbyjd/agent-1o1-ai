<?php

namespace App\Enums\Billing;

/**
 * Keys looked up against `Plan.features` (a plain `[key => bool]` JSON map)
 * via `Plan::hasFeature()`.
 */
enum Feature: string
{
    case CreditPacks = 'credit_packs';
}
