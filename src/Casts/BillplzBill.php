<?php

namespace Izzudin96\Billplz\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Izzudin96\Billplz\DTOs\BillResponse;

class BillplzBill implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?BillResponse
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                return null;
            }

            return BillResponse::fromArray($decoded);
        }

        if (is_array($value)) {
            return BillResponse::fromArray($value);
        }

        return null;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BillResponse) {
            return json_encode($value);
        }

        if (is_array($value)) {
            return json_encode(BillResponse::fromArray($value));
        }

        return null;
    }
}
