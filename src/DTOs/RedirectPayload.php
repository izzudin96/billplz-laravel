<?php

namespace Izzudin96\Billplz\DTOs;

final readonly class RedirectPayload
{
    public function __construct(
        public ?string $id = null,
        public ?string $paid_at = null,
        public ?bool $paid = null,
        public ?string $transaction_id = null,
        public ?string $transaction_status = null,
        public ?string $x_signature = null,
        public ?bool $signature_valid = null,
        public array $extra = []
    ) {
    }

    public static function fromArray(array $payload, ?bool $signatureValid = null): self
    {
        $knownKeys = [
            'id',
            'paid_at',
            'paid',
            'transaction_id',
            'transaction_status',
            'x_signature',
        ];

        return new self(
            id: isset($payload['id']) ? (string) $payload['id'] : null,
            paid_at: isset($payload['paid_at']) ? (string) $payload['paid_at'] : null,
            paid: array_key_exists('paid', $payload) ? self::normalizeBoolean($payload['paid']) : null,
            transaction_id: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            transaction_status: isset($payload['transaction_status']) ? (string) $payload['transaction_status'] : null,
            x_signature: isset($payload['x_signature']) ? (string) $payload['x_signature'] : null,
            signature_valid: $signatureValid,
            extra: array_diff_key($payload, array_flip($knownKeys)),
        );
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
