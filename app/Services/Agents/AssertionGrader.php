<?php

namespace App\Services\Agents;

use App\Ai\Agents\EvalJudgeAgent;
use App\Enums\Agents\EvalAssertionType;
use Throwable;

/**
 * Grades one expectation against one answer.
 *
 * String assertions are compared case-insensitively: an eval that fails
 * because the agent wrote "Refund" instead of "refund" is testing
 * capitalisation, which is not what anyone writes a `contains` assertion for.
 * A test that really cares about exact casing belongs in an `llm_rubric`
 * that says so.
 */
class AssertionGrader
{
    /**
     * @param  array<string, mixed>  $assertion  `{type, value}`
     * @return array{type: string, value: string, passed: bool, error: string|null}
     */
    public function grade(array $assertion, string $output): array
    {
        $type = EvalAssertionType::tryFrom($assertion['type'] ?? '');
        $value = (string) ($assertion['value'] ?? '');

        if ($type === null) {
            return $this->result($assertion['type'] ?? '', $value, false, "Unknown assertion type '".($assertion['type'] ?? '')."'.");
        }

        if (! $type->needsJudge()) {
            return $this->result($type->value, $value, $this->gradeLiteral($type, $value, $output), null);
        }

        try {
            return $this->result($type->value, $value, $this->gradeWithJudge($value, $output), null);
        } catch (Throwable $e) {
            // A judge that couldn't be reached is a failed *assertion*, not a
            // failed suite: the rest of the cases still carry information, and
            // reporting the reason beats reporting a silent pass.
            return $this->result($type->value, $value, false, "Judge unavailable: {$e->getMessage()}");
        }
    }

    private function gradeLiteral(EvalAssertionType $type, string $value, string $output): bool
    {
        $haystack = mb_strtolower($output);
        $needle = mb_strtolower($value);

        return match ($type) {
            EvalAssertionType::Contains => str_contains($haystack, $needle),
            EvalAssertionType::NotContains => ! str_contains($haystack, $needle),
            EvalAssertionType::Equals => trim($haystack) === trim($needle),
            EvalAssertionType::LlmRubric => false,
        };
    }

    private function gradeWithJudge(string $rubric, string $output): bool
    {
        $response = (new EvalJudgeAgent)->prompt(EvalJudgeAgent::promptFor($rubric, $output));

        return EvalJudgeAgent::verdictFromText($response->text);
    }

    /**
     * @return array{type: string, value: string, passed: bool, error: string|null}
     */
    private function result(string $type, string $value, bool $passed, ?string $error): array
    {
        return ['type' => $type, 'value' => $value, 'passed' => $passed, 'error' => $error];
    }
}
