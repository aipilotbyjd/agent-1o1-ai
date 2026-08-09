<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Triggers\TriggerType;
use App\Http\Controllers\Controller;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerService;
use App\Services\Triggers\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, token-authenticated — see docs/TRIGGERS_PLAN.md's "Why webhooks.php
 * sits outside routes/api/{internal,public}/". No session, no API key: the
 * `{token}` path segment against `triggers.token` is the entire auth story.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
        private readonly TriggerService $triggers,
    ) {}

    public function __invoke(string $token, Request $request): Response
    {
        $trigger = Trigger::query()
            ->where('token', $token)
            ->where('type', TriggerType::Webhook)
            ->where('is_active', true)
            ->first();

        abort_if($trigger === null, 404);

        $rawBody = $request->getContent();
        $headers = $this->flattenHeaders($request);
        $snippet = substr($rawBody, 0, 5000);

        if (! $this->verifier->verify($trigger, $request, $rawBody)) {
            $this->triggers->reject(
                $trigger,
                TriggerType::Webhook,
                (array) $request->all(),
                $snippet,
                $headers,
                'Signature verification failed.',
            );

            return response('Invalid signature.', 401);
        }

        $this->triggers->receive(
            $trigger,
            TriggerType::Webhook,
            (array) $request->all(),
            $this->deliveryId($trigger, $request),
            $snippet,
            $headers,
        );

        // Ignored/duplicate/skipped all answer 200 too — providers fan every
        // event at one URL, and a non-2xx on an unwanted event causes retries,
        // then hook disabling. See docs/TRIGGERS_PLAN.md's design decisions.
        return response()->json(['status' => 'accepted'], 200);
    }

    private function deliveryId(Trigger $trigger, Request $request): ?string
    {
        $preset = $trigger->preset;

        if ($preset?->dedupe_header !== null) {
            $value = $request->header($preset->dedupe_header);

            if ($value !== null) {
                return $value;
            }
        }

        if ($preset?->dedupe_payload_path !== null) {
            $value = data_get($request->all(), $preset->dedupe_payload_path);

            if ($value !== null) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function flattenHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values): string => $values[0] ?? '')
            ->all();
    }
}
