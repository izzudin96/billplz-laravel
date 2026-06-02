<?php

namespace Izzudin96\Billplz\DTOs;

use Izzudin96\Billplz\Concerns\NormalizesBooleans;
use JsonSerializable;

final readonly class WebhookPayload implements JsonSerializable
{
    use NormalizesBooleans;
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
            amount: isset($payload['amount']) ? (string) $payload['amount'] : null,
            collection_id: isset($payload['collection_id']) ? (string) $payload['collection_id'] : null,
            due_at: isset($payload['due_at']) ? (string) $payload['due_at'] : null,
            email: isset($payload['email']) ? (string) $payload['email'] : null,
            id: isset($payload['id']) ? (string) $payload['id'] : null,
            mobile: isset($payload['mobile']) ? (string) $payload['mobile'] : null,
            name: isset($payload['name']) ? (string) $payload['name'] : null,
            paid_amount: isset($payload['paid_amount']) ? (string) $payload['paid_amount'] : null,
            paid_at: isset($payload['paid_at']) ? (string) $payload['paid_at'] : null,
            paid: array_key_exists('paid', $payload) ? self::normalizeBoolean($payload['paid']) : null,
            state: isset($payload['state']) ? (string) $payload['state'] : null,
            transaction_id: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            transaction_status: isset($payload['transaction_status']) ? (string) $payload['transaction_status'] : null,
            url: isset($payload['url']) ? (string) $payload['url'] : null,
            x_signature: isset($payload['x_signature']) ? (string) $payload['x_signature'] : null,
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
