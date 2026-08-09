<?php

namespace App\Models\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One append-only ledger row — never updated after creation. Written only
 * via `Actions\Billing\DeductCreditsAction`.
 */
#[Fillable(['workspace_id', 'source_type', 'source_id', 'credits', 'reason'])]
class CreditTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => CreditTransactionType::class,
            'credits' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
