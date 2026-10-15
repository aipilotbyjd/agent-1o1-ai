<x-super-admin.layout :title="'Code '.$code->code" :description="$code->user?->email" :back="route('super-admin.referrals.codes.index')" back-label="Codes">
    <form method="POST" action="{{ route('super-admin.referrals.codes.update', $code) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-super-admin.form-errors />

        <x-super-admin.card title="Code settings">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <div>
                    <label for="code" class="sa-label">Code</label>
                    <input id="code" name="code" type="text" required minlength="3" maxlength="32" value="{{ old('code', $code->code) }}" class="sa-input font-mono">
                </div>
                <div>
                    <label for="program_id" class="sa-label">Program</label>
                    <select id="program_id" name="program_id" class="sa-input pr-8">
                        <option value="">Default program</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('program_id', $code->program_id) === $program->id)>{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm text-zinc-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="size-4 rounded border-zinc-300 accent-zinc-900" @checked((bool) old('is_active', $code->is_active))>
                    Active
                </label>
                <div>
                    <label for="max_uses" class="sa-label">Max uses (blank = unlimited)</label>
                    <input id="max_uses" name="max_uses" type="number" min="1" value="{{ old('max_uses', $code->max_uses) }}" class="sa-input">
                </div>
                <div>
                    <label for="expires_at" class="sa-label">Expires</label>
                    <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', $code->expires_at?->format('Y-m-d\TH:i')) }}" class="sa-input">
                </div>
                <div>
                    <label for="rule_multiplier" class="sa-label">Reward multiplier</label>
                    <input id="rule_multiplier" name="rule_multiplier" type="number" min="0" max="100" step="0.1" required value="{{ old('rule_multiplier', $code->rule_multiplier) }}" class="sa-input">
                </div>
                <div class="sm:col-span-3">
                    <label for="reward_workspace_id" class="sa-label">Rewards land in</label>
                    <select id="reward_workspace_id" name="reward_workspace_id" class="sa-input pr-8">
                        <option value="">Their current workspace</option>
                        @foreach ($code->user?->ownedWorkspaces ?? [] as $workspace)
                            <option value="{{ $workspace->id }}" @selected(old('reward_workspace_id', $code->reward_workspace_id) === $workspace->id)>{{ $workspace->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-super-admin.card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('super-admin.referrals.codes.index') }}" class="sa-btn-secondary">Cancel</a>
            <button type="submit" class="sa-btn-primary">Save code</button>
        </div>
    </form>
</x-super-admin.layout>
