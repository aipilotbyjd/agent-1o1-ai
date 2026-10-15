<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Adds to a workspace's top-up credits. Capped per grant so a typo can't
 * hand out an unbounded balance; the reason lands in the audit log.
 */
class GrantWorkspaceCreditsRequest extends FormRequest
{
    public const int MAX_CREDITS = 1_000_000;

    /**
     * @var string
     */
    protected $errorBag = 'grantCredits';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credits' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CREDITS],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'current_password' => ['required', 'string', 'current_password:super_admin'],
        ];
    }
}
