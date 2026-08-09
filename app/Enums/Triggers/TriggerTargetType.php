<?php

namespace App\Enums\Triggers;

/**
 * The two kinds of thing a trigger can point at. Registered in the morph map
 * (see AppServiceProvider::configureMorphMap()) so `triggers.target_type` stores
 * these short values instead of a fully-qualified class name.
 */
enum TriggerTargetType: string
{
    case Workflow = 'workflow';
    case Agent = 'agent';
}
