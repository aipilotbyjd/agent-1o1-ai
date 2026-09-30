<?php

namespace App\Console\Commands\Admin;

use App\Models\Admin\AdminAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdminAuditLogCommand extends Command
{
    protected $signature = 'admin:audit-log
        {--action= : Only actions starting with this, e.g. referral_rule}
        {--limit=25 : How many entries to show}';

    protected $description = 'Shows recent changes made by platform admins, from the API or these commands.';

    public function handle(): int
    {
        $logs = AdminAuditLog::query()
            ->with('admin:id,email')
            ->when($this->option('action') !== null, fn ($query) => $query->where('action', 'like', $this->option('action').'%'))
            ->latest('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $summarise = fn (?array $values): string => $values === null
            ? '—'
            : Str::limit(collect($values)->map(fn ($value, $key): string => "{$key}: ".(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)))->implode(', '), 60);

        $this->table(
            ['When', 'Who', 'Action', 'Subject', 'Before', 'After'],
            $logs->map(fn (AdminAuditLog $log): array => [
                $log->created_at?->format('Y-m-d H:i'),
                $log->admin?->email ?? 'command line',
                $log->action,
                $log->subject_type ? "{$log->subject_type} ".Str::substr((string) $log->subject_id, -8) : '—',
                $summarise($log->before),
                $summarise($log->after),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
