<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantBriefingType;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\UpdateBriefingConfigRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingConfig;
use App\Models\Assistant\AssistantBriefingRun;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Briefings\BriefingScheduler;
use App\Services\Assistant\Briefings\BriefingSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Daily report: its settings, running it now, and past reports.
 */
class AssistantBriefingController extends Controller
{
    use ResolvesOwnAssistant;

    private const int RUNS_SHOWN = 20;

    public function __construct(
        private BriefingScheduler $scheduler,
        private BriefingSources $sources,
    ) {}

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        return ApiResponse::success($this->payload($this->config($this->ownAssistant($request, $workspace))));
    }

    public function update(UpdateBriefingConfigRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $config = $this->config($this->ownAssistant($request, $workspace));
        $config->fill($request->validated())->save();

        return ApiResponse::success($this->payload($config->refresh()), 'Saved.');
    }

    public function pause(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $config = $this->config($this->ownAssistant($request, $workspace));
        $config->forceFill(['paused_at' => now()])->save();

        return ApiResponse::success($this->payload($config), 'Paused.');
    }

    public function resume(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $config = $this->config($this->ownAssistant($request, $workspace));
        $config->forceFill(['paused_at' => null])->save();

        return ApiResponse::success($this->payload($config), 'Resumed.');
    }

    public function runNow(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $run = $this->scheduler->runNow($this->config($this->ownAssistant($request, $workspace)));

        return ApiResponse::success(['run' => $run === null ? null : $this->describeRun($run->refresh())], 'Writing your report.', Response::HTTP_ACCEPTED);
    }

    public function showRun(Request $request, Workspace $workspace, AssistantBriefingRun $run): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        abort_if($run->config->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);

        return ApiResponse::success(['run' => $this->describeRun($run, withDocument: true)]);
    }

    private function config(Assistant $assistant): AssistantBriefingConfig
    {
        return $assistant->briefingConfigs()->firstOrCreate(
            ['type' => AssistantBriefingType::Daily],
            [
                'enabled' => false,
                'schedule' => ['time' => '08:00', 'days' => [1, 2, 3, 4, 5], 'timezone' => 'UTC'],
                'connector_scope' => 'all',
                'delivery' => ['email' => false],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AssistantBriefingConfig $config): array
    {
        return [
            'config' => [
                'enabled' => $config->enabled,
                'paused' => $config->paused_at !== null,
                'schedule' => $config->schedule,
                'connector_scope' => $config->connector_scope,
                'connector_keys' => $config->connector_keys ?? [],
                'instructions' => $config->instructions,
                'delivery' => $config->delivery ?? ['email' => false],
            ],
            'readable_sources' => array_keys($this->sources->all()),
            'runs' => $config->runs()->latest()->limit(self::RUNS_SHOWN)->get()
                ->map(fn (AssistantBriefingRun $run): array => $this->describeRun($run))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeRun(AssistantBriefingRun $run, bool $withDocument = false): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'trigger' => $run->trigger,
            'summary' => $run->summary,
            'document' => $this->when($withDocument, $run->document),
            'sources' => $run->source_results ?? [],
            'error' => $run->error,
            'situations_count' => $run->situations()->count(),
            'created_at' => $run->created_at,
            'delivered_at' => $run->delivered_at,
        ];
    }

    private function when(bool $condition, mixed $value): mixed
    {
        return $condition ? $value : null;
    }
}
