<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReferralProgramRequest;
use App\Http\Resources\Api\Internal\V1\Admin\ReferralProgramResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralProgram;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create, tune, schedule and retire referral programs. Deleting is a soft
 * delete: referrals made under a program keep their terms and history.
 */
class ReferralProgramController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function index(Request $request)
    {
        $programs = ReferralProgram::query()
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->withCount(['rules', 'referrals'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(['programs' => ReferralProgramResource::collection($programs)]);
    }

    public function store(ReferralProgramRequest $request)
    {
        $data = $request->validated();
        $data['slug'] ??= $this->uniqueSlug($data['name']);

        $program = ReferralProgram::query()->create($data);

        $this->audit->record($request->user(), 'referral_program.created', $program, null, $program->attributesToArray());

        return ApiResponse::created(['program' => ReferralProgramResource::make($program->load('rules'))], 'Referral program created.');
    }

    public function show(ReferralProgram $program)
    {
        $program->load(['rules.plan'])->loadCount(['rules', 'referrals']);

        return ApiResponse::success(['program' => ReferralProgramResource::make($program)]);
    }

    public function update(ReferralProgramRequest $request, ReferralProgram $program)
    {
        $original = $program->getAttributes();
        $data = $request->validated();

        if (array_key_exists('fraud_checks', $data) && $data['fraud_checks'] !== null) {
            $data['fraud_checks'] = array_replace_recursive($program->fraud_checks ?? [], $data['fraud_checks']);
        }

        $program->update($data);

        $this->audit->recordChanges($request->user(), 'referral_program.updated', $program, $original);

        return ApiResponse::success(['program' => ReferralProgramResource::make($program->refresh()->load('rules'))], 'Referral program updated.');
    }

    public function destroy(Request $request, ReferralProgram $program)
    {
        abort_if($program->is_default, 422, 'Make another program the default before deleting this one.');

        $program->delete();

        $this->audit->record($request->user(), 'referral_program.deleted', $program);

        return ApiResponse::success(null, 'Referral program deleted.');
    }

    public function makeDefault(Request $request, ReferralProgram $program)
    {
        abort_unless($program->is_active, 422, 'Only an active program can be the default.');

        $previous = ReferralProgram::query()->where('is_default', true)->value('id');

        $program->update(['is_default' => true]);

        $this->audit->record($request->user(), 'referral_program.made_default', $program, ['default_program_id' => $previous], ['default_program_id' => $program->id]);

        return ApiResponse::success(['program' => ReferralProgramResource::make($program->refresh())], 'Default referral program changed.');
    }

    /**
     * Copies a program and all its rules — the quick way to build a
     * campaign from the default. The copy starts inactive and not default,
     * so nothing changes until it is switched on.
     */
    public function duplicate(Request $request, ReferralProgram $program)
    {
        $copy = DB::transaction(function () use ($program): ReferralProgram {
            $copy = $program->replicate(['is_default', 'is_active']);
            $copy->name = "{$program->name} (copy)";
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->is_default = false;
            $copy->is_active = false;
            $copy->save();

            foreach ($program->rules as $rule) {
                $copy->rules()->save($rule->replicate());
            }

            return $copy;
        });

        $this->audit->record($request->user(), 'referral_program.duplicated', $copy, ['source_program_id' => $program->id], $copy->attributesToArray());

        return ApiResponse::created(['program' => ReferralProgramResource::make($copy->load('rules'))], 'Referral program duplicated.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'program';
        $slug = $base;
        $suffix = 2;

        while (ReferralProgram::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
