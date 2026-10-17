<x-super-admin.layout title="Credit charges" description="The credit ledger: every charge billed to a workspace">
    <x-super-admin.filters :action="route('super-admin.billing.credits.index')" :active="(bool) array_filter($filters)">
        <x-super-admin.filter-select name="type" label="Charged for" placeholder="Anything" :value="$filters['type'] ?? null" :options="\App\Enums\Billing\CreditTransactionType::class" />
        <x-super-admin.filter-dates :from="$filters['from'] ?? null" :to="$filters['to'] ?? null" />
        <x-super-admin.filter-text name="workspace" label="Workspace" :value="$filters['workspace'] ?? null" placeholder="Paste a workspace ID" mono class="w-full sm:w-96" />
    </x-super-admin.filters>

    <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach (['Credits charged' => $totals['credits'], 'From top-up pool' => $totals['topup_credits'], 'Overage' => $totals['overage_credits']] as $label => $value)
            <x-super-admin.stat :label="$label">{{ number_format($value) }}</x-super-admin.stat>
        @endforeach
    </section>

    <x-super-admin.card class="mt-6">
        @if ($transactions->isEmpty())
            <x-super-admin.empty :clear="array_filter($filters) ? route('super-admin.billing.credits.index') : null" icon="receipt">No charges match.</x-super-admin.empty>
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
                            <tr>
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

    <x-super-admin.pagination :paginator="$transactions" />
</x-super-admin.layout>
