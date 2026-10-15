<x-super-admin.layout title="Blocked domains" description="Signups from these email domains never earn referral rewards">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Block a domain">
            <form method="POST" action="{{ route('super-admin.referrals.blocked-domains.store') }}" class="space-y-3 px-5 py-4">
                @csrf
                <div>
                    <label for="domain" class="sa-label">Domain</label>
                    <input id="domain" name="domain" type="text" required maxlength="255" value="{{ old('domain') }}" placeholder="example.com" class="sa-input font-mono">
                </div>
                <div>
                    <label for="reason" class="sa-label">Reason (optional)</label>
                    <input id="reason" name="reason" type="text" maxlength="255" value="{{ old('reason') }}" class="sa-input">
                </div>
                <x-super-admin.form-errors />
                <button type="submit" class="sa-btn-primary w-full">Block domain</button>
            </form>
        </x-super-admin.card>

        <x-super-admin.card :title="$domains->count().' blocked'" class="lg:col-span-2">
            @if ($domains->isEmpty())
                <x-super-admin.empty icon="lock">No domains blocked.</x-super-admin.empty>
            @else
                <ul class="divide-y divide-zinc-100 text-sm">
                    @foreach ($domains as $domain)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-semibold">{{ $domain->domain }}</p>
                                @if ($domain->reason)
                                    <p class="truncate text-xs text-zinc-500">{{ $domain->reason }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('super-admin.referrals.blocked-domains.destroy', $domain) }}" data-confirm="Unblock {{ $domain->domain }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sa-btn-secondary">Unblock</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-super-admin.card>
    </div>
</x-super-admin.layout>
