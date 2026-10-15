<x-super-admin.layout title="Credit charges" description="The credit ledger: every charge billed to a workspace">
    <x-super-admin.filters :action="route('super-admin.billing.credits.index')" :active="(bool) array_filter($filters)">
        <div class="min-w-72 flex-1">
            <label for="workspace" class="sa-label">Workspace ID</label>
            <input id="workspace" name="workspace" type="text" value="{{ $filters['workspace'] ?? '' }}" class="sa-input font-mono">
        </div>
        <div>
            <label for="type" class="sa-label">Charged for</label>
            <select id="type" name="type" class="sa-input pr-8">
                <option value="">Anything</option>
                @foreach (\App\Enums\Billing\CreditTransactionType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ str_replace('_', ' ', $type->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="sa-label">From</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="sa-input">
        </div>
        <div>
            <label for="to" class="sa-label">To</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="sa-input">
        </div>
    </x-super-admin.filters>

    <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach (['Credits charged' => $totals['credits'], 'From top-up pool' => $totals['topup_credits'], 'Overage' => $totals['overage_credits']] as $label => $value)
            <div class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-zinc-200/80">
                <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
                <p class="mt-2 text-xl font-semibold tracking-tight tabular-nums">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </section>

    <x-super-admin.card class="mt-6">
        @if ($transactions->isEmpty())
            <x-super-admin.empty icon="coins">No charges match.</x-super-admin.empty>
        @else
            <div class="overflow-x-auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Workspace</th>
                            <th>Charged for</th>
                            <th class="text-right">Credits</th>
                            <th class="text-right">Top-up</th>
                            <th class="text-right">Overage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr class="hover:bg-zinc-50/70">
                                <td class="whitespace-nowrap text-zinc-500" title="{{ $transaction->created_at }}">{{ $transaction->created_at?->diffForHumans() }}</td>
                                <td>
                                    @if ($transaction->workspace)
                                        <a href="{{ route('super-admin.workspaces.show', $transaction->workspace) }}" class="sa-link">{{ $transaction->workspace->name }}</a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="capitalize">{{ str_replace('_', ' ', $transaction->source_type?->value ?? '—') }}</td>
                                <td class="text-right font-mono tabular-nums">{{ number_format($transaction->credits) }}</td>
                                <td class="text-right font-mono text-zinc-500 tabular-nums">{{ number_format($transaction->topup_credits) }}</td>
                                <td class="text-right font-mono text-zinc-500 tabular-nums">{{ number_format($transaction->overage_credits) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-super-admin.card>

    <div class="mt-4">{{ $transactions->links() }}</div>
</x-super-admin.layout>
