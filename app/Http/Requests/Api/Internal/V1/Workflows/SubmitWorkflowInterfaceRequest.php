<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A form submission against a workflow's declared interface. Unlike
 * `StartRunRequest` — which accepts any input array, because an API caller
 * or a webhook payload is trusted to know the shape — this validates against
 * the contract the workflow published, so a person filling in a form gets a
 * 422 naming the field rather than a run that quietly resolves `{{ input.x }}`
 * to null.
 */
class SubmitWorkflowInterfaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workflow = $this->route('workflow');

        return [
            'input' => ['nullable', 'array'],
            ...($workflow instanceof Workflow ? app(WorkflowInterface::class)->rules($workflow) : []),
        ];
    }
}
