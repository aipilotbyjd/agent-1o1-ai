<x-super-admin.layout :title="'Code '.$code->code" :description="$code->user?->email" :back="route('super-admin.referrals.codes.index')" back-label="Codes">
    <form method="POST" action="{{ route('super-admin.referrals.codes.update', $code) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-super-admin.form-errors />

        <x-super-admin.card title="Code settings">
            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">
                <x-super-admin.field for="code" label="Code">
                    <input id="code" name="code" type="text" required minlength="3" maxlength="32" value="{{ old('code', $code->code) }}" class="sa-input font-mono">
                </x-super-admin.field>
                <x-super-admin.field for="program_id" label="Program">
                    <select id="program_id" name="program_id" class="sa-input pr-8">
                        <option value="">Default program</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('program_id', $code->program_id) === $program->id)>{{ $program->name }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
                <x-super-admin.switch name="is_active" label="Active" description="Inactive codes stop attributing signups." :checked="(bool) old('is_active', $code->is_active)" class="self-end" />
                <x-super-admin.field for="max_uses" label="Max uses" hint="Leave blank for unlimited.">
                    <input id="max_uses" name="max_uses" type="number" min="1" value="{{ old('max_uses', $code->max_uses) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="expires_at" label="Expires">
                    <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', $code->expires_at?->format('Y-m-d\TH:i')) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="rule_multiplier" label="Reward multiplier" hint="Scales every reward this code earns. 1 is normal.">
                    <input id="rule_multiplier" name="rule_multiplier" type="number" min="0" max="100" step="0.1" required value="{{ old('rule_multiplier', $code->rule_multiplier) }}" class="sa-input">
                </x-super-admin.field>
                <x-super-admin.field for="reward_workspace_id" label="Rewards land in" class="sm:col-span-3">
                    <select id="reward_workspace_id" name="reward_workspace_id" class="sa-input pr-8">
                        <option value="">Their current workspace</option>
                        @foreach ($code->user?->ownedWorkspaces ?? [] as $workspace)
                            <option value="{{ $workspace->id }}" @selected(old('reward_workspace_id', $code->reward_workspace_id) === $workspace->id)>{{ $workspace->name }}</option>
                        @endforeach
                    </select>
                </x-super-admin.field>
            </div>
        </x-super-admin.card>

        <x-super-admin.form-actions :cancel="route('super-admin.referrals.codes.index')">
            <button type="submit" class="sa-btn-primary">Save code</button>
        </x-super-admin.form-actions>
    </form>
</x-super-admin.layout>
