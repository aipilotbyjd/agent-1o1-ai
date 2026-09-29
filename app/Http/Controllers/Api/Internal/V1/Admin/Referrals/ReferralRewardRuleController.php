<?php

namespace App\Http\Controllers\Api\Internal\V1\Admin\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReferralRewardRuleRequest;
use App\Http\Requests\Api\Internal\V1\Admin\Referrals\ReorderReferralRewardRulesRequest;
use App\Http\Resources\Api\Internal\V1\Admin\ReferralRewardRuleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Referrals\ReferralProgram;
use App\Models\Referrals\ReferralRewardRule;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reward rules: "when X happens, give Y to Z". Edits never change rewards
 * already earned — each reward keeps a snapshot of its rule.
 */
class ReferralRewardRuleController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function index(ReferralProgram $program)
    {
        return ApiResponse::success(['rules' => ReferralRewardRuleResource::collection($program->rules()->with('plan')->get())]);
    }

    public function store(ReferralRewardRuleRequest $request, ReferralProgram $program)
    {
        $data = $request->validated();
        $data['sort_order'] ??= (int) $program->rules()->max('sort_order') + 1;

        $rule = $program->rules()->create($data);

        $this->audit->record($request->user(), 'referral_rule.created', $rule, null, $rule->attributesToArray());

        return ApiResponse::created(['rule' => ReferralRewardRuleResource::make($rule->load('plan'))], 'Reward rule created.');
    }

    public function update(ReferralRewardRuleRequest $request, ReferralRewardRule $rule)
    {
        $original = $rule->getAttributes();

        $rule->update($request->validated());

        $this->audit->recordChanges($request->user(), 'referral_rule.updated', $rule, $original);

        return ApiResponse::success(['rule' => ReferralRewardRuleResource::make($rule->refresh()->load('plan'))], 'Reward rule updated.');
    }

    public function destroy(Request $request, ReferralRewardRule $rule)
    {
        $rule->delete();

        $this->audit->record($request->user(), 'referral_rule.deleted', $rule, $rule->attributesToArray());

        return ApiResponse::success(null, 'Reward rule deleted.');
    }

    public function toggle(Request $request, ReferralRewardRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        $this->audit->record($request->user(), 'referral_rule.toggled', $rule, ['is_active' => ! $rule->is_active], ['is_active' => $rule->is_active]);

        return ApiResponse::success(['rule' => ReferralRewardRuleResource::make($rule)], $rule->is_active ? 'Reward rule switched on.' : 'Reward rule switched off.');
    }

    public function reorder(ReorderReferralRewardRulesRequest $request, ReferralProgram $program)
    {
        $ids = $request->validated('rule_ids');

        abort_unless(
            $program->rules()->whereIn('id', $ids)->count() === count($ids),
            422,
            'Every rule must belong to this program.',
        );

        DB::transaction(function () use ($program, $ids): void {
            foreach ($ids as $position => $id) {
                $program->rules()->whereKey($id)->first()?->update(['sort_order' => $position]);
            }
        });

        $this->audit->record($request->user(), 'referral_rule.reordered', $program, null, ['rule_ids' => $ids]);

        return ApiResponse::success(['rules' => ReferralRewardRuleResource::collection($program->rules()->with('plan')->get())], 'Reward rules reordered.');
    }
}
