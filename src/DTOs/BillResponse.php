<?php

namespace Izzudin96\Billplz\DTOs;

use Izzudin96\Billplz\Concerns\NormalizesBooleans;
use JsonSerializable;

final readonly class BillResponse implements JsonSerializable
{
    use NormalizesBooleans;
    public function __construct(
        public ?string $id = null,
        public ?string $url = null,
        public ?string $collection_id = null,
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $name = null,
        public ?int $amount = null,
        public ?string $description = null,
        public ?string $callback_url = null,
        public ?string $redirect_url = null,
        public mixed $reference_1 = null,
        public mixed $reference_1_label = null,
        public mixed $reference_2 = null,
        public mixed $reference_2_label = null,
        public ?string $paid_at = null,
        public ?bool $paid = null,
        public ?string $state = null,
        public ?string $transaction_id = null,
        public ?string $transaction_status = null,
        public ?string $x_signature = null,
        public array $extra = []
    ) {
    }

    public static function fromArray(array $payload): self
    {
        $knownKeys = [
            'id',
            'url',
            'collection_id',
            'email',
            'mobile',
            'name',
            'amount',
            'description',
            'callback_url',
            'redirect_url',
            'reference_1',
            'reference_1_label',
            'reference_2',
            'reference_2_label',
            'paid_at',
            'paid',
            'state',
            'transaction_id',
            'transaction_status',
            'x_signature',
        ];

        $extra = array_diff_key($payload, array_flip($knownKeys));

        return new self(
            id: isset($payload['id']) ? (string) $payload['id'] : null,
            url: isset($payload['url']) ? (string) $payload['url'] : null,
            collection_id: isset($payload['collection_id']) ? (string) $payload['collection_id'] : null,
            email: isset($payload['email']) ? (string) $payload['email'] : null,
            mobile: isset($payload['mobile']) ? (string) $payload['mobile'] : null,
            name: isset($payload['name']) ? (string) $payload['name'] : null,
            amount: isset($payload['amount']) && is_numeric($payload['amount']) ? (int) $payload['amount'] : null,
            description: isset($payload['description']) ? (string) $payload['description'] : null,
            callback_url: isset($payload['callback_url']) ? (string) $payload['callback_url'] : null,
            redirect_url: isset($payload['redirect_url']) ? (string) $payload['redirect_url'] : null,
            reference_1: $payload['reference_1'] ?? null,
            reference_1_label: $payload['reference_1_label'] ?? null,
            reference_2: $payload['reference_2'] ?? null,
            reference_2_label: $payload['reference_2_label'] ?? null,
            paid_at: isset($payload['paid_at']) ? (string) $payload['paid_at'] : null,
            paid: array_key_exists('paid', $payload) ? self::normalizeBoolean($payload['paid']) : null,
            state: isset($payload['state']) ? (string) $payload['state'] : null,
            transaction_id: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            transaction_status: isset($payload['transaction_status']) ? (string) $payload['transaction_status'] : null,
            x_signature: isset($payload['x_signature']) ? (string) $payload['x_signature'] : null,
            extra: $extra,
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'url' => $this->url,
            'collection_id' => $this->collection_id,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'name' => $this->name,
            'amount' => $this->amount,
            'description' => $this->description,
            'callback_url' => $this->callback_url,
            'redirect_url' => $this->redirect_url,
            'reference_1' => $this->reference_1,
            'reference_1_label' => $this->reference_1_label,
            'reference_2' => $this->reference_2,
            'reference_2_label' => $this->reference_2_label,
            'paid_at' => $this->paid_at,
            'paid' => $this->paid,
            'state' => $this->state,
            'transaction_id' => $this->transaction_id,
            'transaction_status' => $this->transaction_status,
            'x_signature' => $this->x_signature,
        ];

        $data = array_filter($data, static fn ($value) => $value !== null);

        return array_merge($this->extra, $data);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function merge(self $incoming): self
    {
        $merged = array_merge($this->toArray(), $incoming->toArray());

        return self::fromArray($merged);
    }
}
