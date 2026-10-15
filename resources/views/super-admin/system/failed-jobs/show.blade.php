<x-super-admin.layout :title="class_basename($job->job)" :description="$job->job" :back="route('super-admin.system.failed-jobs.index')" back-label="Failed jobs">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-super-admin.card title="Job">
                <dl class="sa-dl">
                    <div><dt>ID</dt><dd class="font-mono text-xs">{{ $job->uuid }}</dd></div>
                    <div><dt>Queue</dt><dd class="font-mono text-xs">{{ $job->queue }}</dd></div>
                    <div><dt>Connection</dt><dd class="font-mono text-xs">{{ $job->connection }}</dd></div>
                    <div><dt>Failed</dt><dd>{{ \Illuminate\Support\Carbon::parse($job->failed_at)->toDayDateTimeString() }}</dd></div>
                </dl>
            </x-super-admin.card>

            <x-super-admin.card title="Actions" description="Needs your password. Logged to the audit log.">
                <form method="POST" class="space-y-3 px-5 py-4">
                    @csrf
                    <div>
                        <label for="current_password" class="sa-label">Your password</label>
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="sa-input">
                        @error('current_password', 'support')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" formaction="{{ route('super-admin.system.failed-jobs.retry', $job->uuid) }}" class="sa-btn-primary flex-1" data-confirm="Push this job back onto the {{ $job->queue }} queue?">
                            <x-super-admin.icon name="refresh" class="size-4" />
                            Retry
                        </button>
                        <button type="submit" formaction="{{ route('super-admin.system.failed-jobs.destroy', $job->uuid) }}" name="_method" value="DELETE" class="sa-btn-secondary flex-1 text-red-700" data-confirm="Delete this failed job? It can't be retried afterwards.">
                            <x-super-admin.icon name="trash" class="size-4" />
                            Delete
                        </button>
                    </div>
                </form>
            </x-super-admin.card>
        </div>

        <x-super-admin.card title="Exception" class="lg:col-span-2">
            <pre class="max-h-[70vh] overflow-auto px-5 py-4 text-xs leading-relaxed whitespace-pre-wrap text-red-800">{{ $job->exception }}</pre>
        </x-super-admin.card>
    </div>
</x-super-admin.layout>
