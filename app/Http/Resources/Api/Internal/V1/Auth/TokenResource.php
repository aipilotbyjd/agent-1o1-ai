<?php

namespace App\Http\Resources\Api\Internal\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Token;

/**
 * @mixin Token
 */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scopes' => $this->scopes,
            'revoked' => $this->revoked,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
