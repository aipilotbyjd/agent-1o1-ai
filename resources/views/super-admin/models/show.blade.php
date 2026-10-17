<x-super-admin.layout :title="$entry->display_name" :description="$entry->slug.' · '.number_format($entry->agents_count).' '.str('agent')->plural($entry->agents_count).' use it'" :back="route('super-admin.models.index')" back-label="Model catalog">
    <x-slot:actions>
        <x-super-admin.status-badge :status="$entry->is_active ? 'active' : 'disabled'" />
        <a href="{{ route('super-admin.models.edit', $entry) }}" class="sa-btn-primary">Edit details</a>
    </x-slot:actions>

    @php $enabled = $entry->routes->where('is_enabled', true); @endphp

    @if ($entry->is_active && $enabled->isEmpty())
        <div class="mb-6 flex items-center gap-2.5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/15">
            <x-super-admin.icon name="warning" class="size-5 text-red-600" />
            No enabled route: every prompt on this model fails until one is enabled.
        </div>
    @endif

    <x-super-admin.form-errors bag="route" class="mb-6" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-super-admin.card title="Routes" description="Tried in priority order (lowest first); the next one takes over on a provider error." class="lg:col-span-2">
            @if ($entry->routes->isEmpty())
                <x-super-admin.empty icon="bolt">No routes yet.</x-super-admin.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Model id</th>
                                <th>Priority · enabled</th>
                                <th class="text-right">Failures</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entry->routes as $route)
                                <tr class="align-top">
                                    <td>
                                        <p class="font-mono text-xs font-semibold">{{ $route->execution_provider }}</p>
                                        @unless ($providers[$route->execution_provider] ?? false)
                                            <p class="text-xs text-amber-700">No API key configured</p>
                                        @endunless
                                    </td>
                                    <td class="font-mono text-xs break-all">{{ $route->execution_model_id }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('super-admin.models.routes.update', [$entry, $route]) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input name="priority" type="number" min="0" max="1000" value="{{ $route->priority }}" class="sa-input mt-0 w-20" aria-label="Priority">
                                            <input type="hidden" name="is_enabled" value="0">
                                            <input type="checkbox" name="is_enabled" value="1" class="sa-switch" @checked($route->is_enabled) aria-label="Enabled">
                                            <button type="submit" class="sa-btn-secondary">Save</button>
                                        </form>
                                    </td>
                                    <td class="text-right">
                                        <p class="tabular-nums">{{ $route->failure_count }}</p>
                                        @if ($route->last_failed_at)
                                            <p class="text-xs text-zinc-500">{{ $route->last_failed_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1.5">
                                            @if ($route->failure_count > 0)
                                                <form method="POST" action="{{ route('super-admin.models.routes.reset-failures', [$entry, $route]) }}">
                                                    @csrf
                                                    <button type="submit" class="sa-btn-secondary">Reset</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('super-admin.models.routes.destroy', [$entry, $route]) }}" data-confirm="Delete the {{ $route->execution_provider }} route?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sa-btn-secondary text-red-700">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-super-admin.card>

        <div class="space-y-6">
            <x-super-admin.card title="Add a route">
                <form method="POST" action="{{ route('super-admin.models.routes.store', $entry) }}" class="space-y-3 px-5 py-4">
                    @csrf
                    <div>
                        <label for="execution_provider" class="sa-label">Provider</label>
                        <select id="execution_provider" name="execution_provider" required class="sa-input pr-8">
                            @foreach ($providers as $provider => $configured)
                                <option value="{{ $provider }}" @selected(old('execution_provider') === $provider)>{{ $provider }}{{ $configured ? '' : ' (no key)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="execution_model_id" class="sa-label">Model id at that provider</label>
                        <input id="execution_model_id" name="execution_model_id" type="text" required maxlength="255" value="{{ old('execution_model_id') }}" class="sa-input font-mono">
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label for="priority" class="sa-label">Priority</label>
                            <input id="priority" name="priority" type="number" min="0" max="1000" value="{{ old('priority', $entry->routes->max('priority') !== null ? $entry->routes->max('priority') + 1 : 0) }}" class="sa-input">
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm text-zinc-700">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox" name="is_enabled" value="1" class="sa-switch" checked>
                            Enabled
                        </label>
                    </div>
                    <button type="submit" class="sa-btn-primary w-full">Save route</button>
                </form>
            </x-super-admin.card>

            <x-super-admin.card title="Details">
                <dl class="sa-dl">
                    <div><dt>Brand</dt><dd class="capitalize">{{ $entry->brand }}</dd></div>
                    <div><dt>Speed</dt><dd>{{ $entry->speed?->value ?? '—' }}</dd></div>
                    <div><dt>Cost tier</dt><dd>{{ $entry->cost_tier ?? '—' }}</dd></div>
                    <div><dt>Context window</dt><dd class="tabular-nums">{{ isset($entry->capabilities['context_window']) ? number_format($entry->capabilities['context_window']) : '—' }}</dd></div>
                    <div><dt>Internal only</dt><dd>{{ $entry->is_internal ? 'Yes' : 'No' }}</dd></div>
                    <div><dt>Retires</dt><dd>{{ $entry->retires_at?->toFormattedDateString() ?? '—' }}{{ $entry->replacement_slug ? ' → '.$entry->replacement_slug : '' }}</dd></div>
                </dl>
            </x-super-admin.card>
        </div>
    </div>
</x-super-admin.layout>
