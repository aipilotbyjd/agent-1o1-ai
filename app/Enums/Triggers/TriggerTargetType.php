<?php

namespace App\Enums\Triggers;

use App\Models\Agents\Agent;
use App\Models\Workflows\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * The two kinds of thing a trigger can point at. Registered in the morph map
 * (see AppServiceProvider::configureMorphMap()) so `triggers.target_type` stores
 * these short values instead of a fully-qualified class name.
 */
enum TriggerTargetType: string
{
    case Workflow = 'workflow';
    case Agent = 'agent';

    /**
     * The model backing this target type — the same class the morph map
     * aliases this value to.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Workflow => Workflow::class,
            self::Agent => Agent::class,
        };
    }
}
