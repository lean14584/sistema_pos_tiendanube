<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'address', 'tax_id', 'tipo_documento'])]
class Provider extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProviderPayment::class);
    }
}
