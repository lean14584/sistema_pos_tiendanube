<?php

namespace App\Models;

use App\Enums\CashMovementSource;
use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_id', 'type', 'concept', 'amount', 'source', 'source_id', 'date'])]
class CashMovement extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => CashMovementType::class,
            'source' => CashMovementSource::class,
            'date' => 'date',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'session_id');
    }
}
