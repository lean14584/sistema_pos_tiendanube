<?php

namespace App\Services\Afip\Data;

use Illuminate\Support\Carbon;

final readonly class CaeResponse
{
    /**
     * @param  string[]  $observaciones
     */
    public function __construct(
        public string $cae,
        public Carbon $caeVencimiento,
        public array $observaciones = [],
        public array $raw = [],
    ) {
    }
}
