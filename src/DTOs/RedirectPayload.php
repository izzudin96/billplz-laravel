<?php

namespace Izzudin96\Billplz\DTOs;

use Izzudin96\Billplz\Concerns\NormalizesBooleans;
use Izzudin96\Billplz\Concerns\NormalizesStrings;
use JsonSerializable;

final readonly class RedirectPayload implements JsonSerializable
{
    use NormalizesBooleans;
    use NormalizesStrings;
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
            id: self::toStringOrNull($payload['id'] ?? null),
            paid_at: self::toStringOrNull($payload['paid_at'] ?? null),
            paid: array_key_exists('paid', $payload) ? self::normalizeBoolean($payload['paid']) : null,
            transaction_id: self::toStringOrNull($payload['transaction_id'] ?? null),
            transaction_status: self::toStringOrNull($payload['transaction_status'] ?? null),
            x_signature: self::toStringOrNull($payload['x_signature'] ?? null),
            signature_valid: $signatureValid,
            extra: array_diff_key($payload, array_flip($knownKeys)),
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'paid_at' => $this->paid_at,
            'paid' => $this->paid,
            'transaction_id' => $this->transaction_id,
            'transaction_status' => $this->transaction_status,
            'x_signature' => $this->x_signature,
            'signature_valid' => $this->signature_valid,
        ];

        $data = array_filter($data, static fn ($value) => $value !== null);

        return array_merge($this->extra, $data);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toBillResponse(): BillResponse
    {
        return BillResponse::fromArray(array_merge([
            'id' => $this->id,
            'paid' => $this->paid,
            'paid_at' => $this->paid_at,
            'transaction_id' => $this->transaction_id,
            'transaction_status' => $this->transaction_status,
            'x_signature' => $this->x_signature,
        ], $this->extra));
    }
}
