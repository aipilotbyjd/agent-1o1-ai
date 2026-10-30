<?php

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\BillingSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached reads and writes of the admin-editable billing switches. A value in
 * `billing_settings` wins over the same key in `config/billing.php`, which
 * stays as the fallback so an install that never touches the admin keeps its
 * env-driven behaviour.
 */
class BillingSettings
{
    private const string CACHE_KEY = 'billing:settings';

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => BillingSetting::query()->pluck('value', 'key')->all(),
        );
    }

    public function intervalEnabled(BillingInterval $interval): bool
    {
        $override = $this->all()["intervals.{$interval->value}"] ?? null;

        if ($override !== null) {
            return (bool) $override;
        }

        return (bool) config("billing.intervals.{$interval->value}", true);
    }

    /**
     * @return array<string, bool>
     */
    public function intervals(): array
    {
        $states = [];

        foreach (BillingInterval::cases() as $interval) {
            $states[$interval->value] = $this->intervalEnabled($interval);
        }

        return $states;
    }

    public function setIntervalEnabled(BillingInterval $interval, bool $enabled): void
    {
        BillingSetting::query()->updateOrCreate(['key' => "intervals.{$interval->value}"], ['value' => $enabled]);

        Cache::forget(self::CACHE_KEY);
    }
}
