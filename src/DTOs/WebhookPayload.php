<?php

namespace Izzudin96\Billplz\DTOs;

use Izzudin96\Billplz\Concerns\NormalizesBooleans;
use Izzudin96\Billplz\Concerns\NormalizesStrings;
use JsonSerializable;

final readonly class WebhookPayload implements JsonSerializable
{
    use NormalizesBooleans;
    use NormalizesStrings;
    public function __construct(
        public ?string $amount = null,
        public ?string $collection_id = null,
        public ?string $due_at = null,
        public ?string $email = null,
        public ?string $id = null,
        public ?string $mobile = null,
        public ?string $name = null,
        public ?string $paid_amount = null,
        public ?string $paid_at = null,
        public ?bool $paid = null,
        public ?string $state = null,
        public ?string $transaction_id = null,
        public ?string $transaction_status = null,
        public ?string $url = null,
        public ?string $x_signature = null,
        public ?bool $signature_valid = null,
        public array $extra = []
    ) {
    }

    public static function fromArray(array $payload, ?bool $signatureValid = null): self
    {
        $knownKeys = [
            'amount',
            'collection_id',
            'due_at',
            'email',
            'id',
            'mobile',
            'name',
            'paid_amount',
            'paid_at',
            'paid',
            'state',
            'transaction_id',
            'transaction_status',
            'url',
            'x_signature',
        ];

        return new self(
            amount: self::toStringOrNull($payload['amount'] ?? null),
            collection_id: self::toStringOrNull($payload['collection_id'] ?? null),
            due_at: self::toStringOrNull($payload['due_at'] ?? null),
            email: self::toStringOrNull($payload['email'] ?? null),
            id: self::toStringOrNull($payload['id'] ?? null),
            mobile: self::toStringOrNull($payload['mobile'] ?? null),
            name: self::toStringOrNull($payload['name'] ?? null),
            paid_amount: self::toStringOrNull($payload['paid_amount'] ?? null),
            paid_at: self::toStringOrNull($payload['paid_at'] ?? null),
            paid: array_key_exists('paid', $payload) ? self::normalizeBoolean($payload['paid']) : null,
            state: self::toStringOrNull($payload['state'] ?? null),
            transaction_id: self::toStringOrNull($payload['transaction_id'] ?? null),
            transaction_status: self::toStringOrNull($payload['transaction_status'] ?? null),
            url: self::toStringOrNull($payload['url'] ?? null),
            x_signature: self::toStringOrNull($payload['x_signature'] ?? null),
            signature_valid: $signatureValid,
            extra: array_diff_key($payload, array_flip($knownKeys)),
        );
    }

    public function toArray(): array
    {
        $data = [
            'amount' => $this->amount,
            'collection_id' => $this->collection_id,
            'due_at' => $this->due_at,
            'email' => $this->email,
            'id' => $this->id,
            'mobile' => $this->mobile,
            'name' => $this->name,
            'paid_amount' => $this->paid_amount,
            'paid_at' => $this->paid_at,
            'paid' => $this->paid,
            'state' => $this->state,
            'transaction_id' => $this->transaction_id,
            'transaction_status' => $this->transaction_status,
            'url' => $this->url,
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
            'amount' => is_numeric($this->amount) ? (int) $this->amount : null,
            'collection_id' => $this->collection_id,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'name' => $this->name,
            'paid_at' => $this->paid_at,
            'paid' => $this->paid,
            'state' => $this->state,
            'transaction_id' => $this->transaction_id,
            'transaction_status' => $this->transaction_status,
            'url' => $this->url,
            'x_signature' => $this->x_signature,
        ], $this->extra));
    }
}
