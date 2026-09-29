<?php

namespace App\Console\Commands\Referrals;

use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Referrals\ReferralRuleDescriber;
use Illuminate\Console\Command;

class ListReferralProgramsCommand extends Command
{
    protected $signature = 'referrals:programs';

    protected $description = 'Lists referral programs and their reward rules in plain English.';

    public function handle(ReferralRuleDescriber $describer): int
    {
        $programs = ReferralProgram::query()->with('rules.plan')->orderByDesc('is_default')->get();

        if ($programs->isEmpty()) {
            $this->warn('No referral programs yet. Run `php artisan db:seed --class=ReferralProgramSeeder` to create the default one.');

            return self::SUCCESS;
        }

        foreach ($programs as $program) {
            $flags = array_filter([
                $program->is_default ? 'default' : null,
                $program->isLive() ? 'live' : 'not live',
                $program->approval_mode->value.' approval',
                "{$program->default_hold_days}-day hold",
            ]);

            $this->newLine();
            $this->line("<info>{$program->name}</info> ({$program->slug}) — ".implode(', ', $flags));

            $this->table(
                ['Order', 'Rule', 'Terms', 'Active'],
                $program->rules->map(fn (ReferralRewardRule $rule): array => [
                    $rule->sort_order,
                    $rule->name,
                    $describer->describe($rule),
                    $rule->isLive() ? 'yes' : 'no',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
