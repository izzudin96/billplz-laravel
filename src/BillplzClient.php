<?php

namespace Izzudin96\Billplz;

use Izzudin96\Billplz\Concerns\NormalizesBooleans;
use Izzudin96\Billplz\DTOs\BillResponse;
use Izzudin96\Billplz\DTOs\RedirectPayload;
use Izzudin96\Billplz\DTOs\WebhookPayload;
use Izzudin96\Billplz\Exceptions\FailedSignatureVerification;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Lightweight BillPlz API client.
 *
 * API reference: https://www.billplz.com/api
 */
class BillplzClient
{
    use NormalizesBooleans;
    /**
     * Ordered parameter names used when computing the redirect X-Signature.
     */
    private const REDIRECT_PARAMETERS = [
        'billplzid',
        'billplzpaid_at',
        'billplzpaid',
        'billplztransaction_id',
        'billplztransaction_status',
    ];

    /**
     * Ordered parameter names used when computing the webhook X-Signature.
     */
    private const WEBHOOK_PARAMETERS = [
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
    ];

    private string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $xSignatureKey,
        private readonly string $collectionId,
        bool $sandbox = false,
        string $version = 'v3',
        private readonly int $timeoutSeconds = 10,
        private readonly int $retryTimes = 1,
        private readonly int $retrySleepMs = 200,
        private readonly string $userAgent = 'billplz-laravel-client'
    ) {
        $version = strtolower(trim($version));
        $version = in_array($version, ['v3', 'v4'], true) ? $version : 'v3';

        $this->baseUrl = $sandbox
            ? "https://www.billplz-sandbox.com/api/{$version}"
            : "https://www.billplz.com/api/{$version}";
    }

    /**
     * Create a new bill in BillPlz.
     *
     * @param  array<string, mixed>  $optional
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function createBill(
        string $email,
        ?string $mobile,
        string $name,
        int $amountCents,
        string $callbackUrl,
        string $description,
        array $optional = []
    ): BillResponse {
        $this->guardApiCredentials();
        $this->guardCollectionId();

        $email = trim($email);
        $name = trim($name);
        $description = trim($description);
        $callbackUrl = trim($callbackUrl);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required to create a Billplz bill.');
        }

        if ($name == '') {
            throw new InvalidArgumentException('Name is required to create a Billplz bill.');
        }

        if ($description == '') {
            throw new InvalidArgumentException('Description is required to create a Billplz bill.');
        }

        if ($callbackUrl == '') {
            throw new InvalidArgumentException('callbackUrl is required to create a Billplz bill.');
        }

        if ($amountCents <= 0) {
            throw new InvalidArgumentException('amountCents must be greater than zero.');
        }

        $mobile = $this->normalizeMobile($mobile);

        $requiredPayload = [
            'collection_id' => $this->collectionId,
            'email' => $email,
            'mobile' => $mobile,
            'name' => $name,
            'amount' => $amountCents,
            'callback_url' => $callbackUrl,
            'description' => $description,
        ];

        $optionalPayload = array_diff_key($optional, array_flip(array_keys($requiredPayload)));
        $payload = array_filter(
            array_merge($optionalPayload, $requiredPayload),
            fn ($v) => $v !== null && $v !== ''
        );

        return BillResponse::fromArray(
            $this->http()
                ->asForm()
                ->post("{$this->baseUrl}/bills", $payload)
                ->throw()
                ->json() ?? []
        );
    }

    /**
     * Retrieve an existing bill from BillPlz.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function getBill(string $billId): BillResponse
    {
        $this->guardApiCredentials();

        $billId = trim($billId);

        if ($billId == '') {
            throw new InvalidArgumentException('billId is required.');
        }

        return BillResponse::fromArray(
            $this->http()
                ->get("{$this->baseUrl}/bills/".rawurlencode($billId))
                ->throw()
                ->json() ?? []
        );
    }

    /**
     * Parse and verify the signed redirect callback from BillPlz.
     *
     * @param  array<string, mixed>  $params
     * @throws FailedSignatureVerification
     */
    public function verifyRedirect(array $params): RedirectPayload
    {
        $billplz = $params['billplz'] ?? null;

        if (! is_array($billplz)) {
            throw new FailedSignatureVerification('Missing billplz parameters in redirect.');
        }

        if ($this->xSignatureKey !== null && ! isset($billplz['x_signature'])) {
            throw new FailedSignatureVerification('Missing billplz x_signature in redirect.');
        }

        $signatureValid = null;

        if ($this->xSignatureKey !== null) {
            $signatureValid = $this->verifySignature(
                $this->buildRedirectSignatureData($billplz),
                self::REDIRECT_PARAMETERS,
                (string) $billplz['x_signature']
            );

            if (! $signatureValid) {
                throw new FailedSignatureVerification;
            }
        }

        return RedirectPayload::fromArray([
            'id' => $billplz['id'] ?? null,
            'paid_at' => $billplz['paid_at'] ?? null,
            'paid' => $this->normalizeBoolean($billplz['paid'] ?? false),
            'transaction_id' => $billplz['transaction_id'] ?? null,
            'transaction_status' => $billplz['transaction_status'] ?? null,
            'x_signature' => $billplz['x_signature'] ?? null,
        ], $signatureValid);
    }

    /**
     * Best-effort redirect parser.
     *
     * Returns null only when required redirect fields are missing.
     * Signature validity is exposed as `signature_valid`.
     *
     * @param  array<string, mixed>  $params
     */
    public function parseRedirect(array $params): ?RedirectPayload
    {
        $billplz = $params['billplz'] ?? null;

        if (! is_array($billplz) || ! isset($billplz['id'])) {
            return null;
        }

        $signatureValid = null;

        if ($this->xSignatureKey !== null) {
            if (! isset($billplz['x_signature'])) {
                $signatureValid = false;
            } else {
                $signatureValid = $this->verifySignature(
                    $this->buildRedirectSignatureData($billplz),
                    self::REDIRECT_PARAMETERS,
                    (string) $billplz['x_signature']
                );
            }
        }

        return RedirectPayload::fromArray([
            'id' => $billplz['id'] ?? null,
            'paid_at' => $billplz['paid_at'] ?? null,
            'paid' => $this->normalizeBoolean($billplz['paid'] ?? false),
            'transaction_id' => $billplz['transaction_id'] ?? null,
            'transaction_status' => $billplz['transaction_status'] ?? null,
            'x_signature' => $billplz['x_signature'] ?? null,
        ], $signatureValid);
    }

    /**
     * Parse and verify the signed webhook POST from BillPlz.
     *
     * @param  array<string, mixed>  $params
     * @throws FailedSignatureVerification
     */
    public function verifyWebhook(array $params): WebhookPayload
    {
        if ($this->xSignatureKey !== null && ! isset($params['x_signature'])) {
            throw new FailedSignatureVerification('Missing x_signature in webhook payload.');
        }

        $signatureValid = null;

        if ($this->xSignatureKey !== null) {
            $signatureValid = $this->verifySignature(
                $this->buildWebhookSignatureData($params),
                self::WEBHOOK_PARAMETERS,
                (string) $params['x_signature']
            );

            if (! $signatureValid) {
                throw new FailedSignatureVerification;
            }
        }

        return WebhookPayload::fromArray([
            'amount' => $params['amount'] ?? null,
            'collection_id' => $params['collection_id'] ?? null,
            'due_at' => $params['due_at'] ?? null,
            'email' => $params['email'] ?? null,
            'id' => $params['id'] ?? null,
            'mobile' => $params['mobile'] ?? null,
            'name' => $params['name'] ?? null,
            'paid_amount' => $params['paid_amount'] ?? null,
            'paid_at' => $params['paid_at'] ?? null,
            'paid' => $this->normalizeBoolean($params['paid'] ?? false),
            'state' => $params['state'] ?? null,
            'transaction_id' => $params['transaction_id'] ?? null,
            'transaction_status' => $params['transaction_status'] ?? null,
            'url' => $params['url'] ?? null,
            'x_signature' => $params['x_signature'] ?? null,
        ], $signatureValid);
    }

    /**
     * Best-effort webhook parser.
     *
     * Webhook should remain authoritative, so this returns null when signature
     * is configured but missing/invalid.
     *
     * @param  array<string, mixed>  $params
     */
    public function parseWebhook(array $params): ?WebhookPayload
    {
        if (! isset($params['id'])) {
            return null;
        }

        $signatureValid = null;

        if ($this->xSignatureKey !== null) {
            if (! isset($params['x_signature'])) {
                return null;
            }

            $signatureValid = $this->verifySignature(
                $this->buildWebhookSignatureData($params),
                self::WEBHOOK_PARAMETERS,
                (string) $params['x_signature']
            );

            if (! $signatureValid) {
                return null;
            }
        }

        return WebhookPayload::fromArray([
            'amount' => $params['amount'] ?? null,
            'collection_id' => $params['collection_id'] ?? null,
            'due_at' => $params['due_at'] ?? null,
            'email' => $params['email'] ?? null,
            'id' => $params['id'] ?? null,
            'mobile' => $params['mobile'] ?? null,
            'name' => $params['name'] ?? null,
            'paid_amount' => $params['paid_amount'] ?? null,
            'paid_at' => $params['paid_at'] ?? null,
            'paid' => $this->normalizeBoolean($params['paid'] ?? false),
            'state' => $params['state'] ?? null,
            'transaction_id' => $params['transaction_id'] ?? null,
            'transaction_status' => $params['transaction_status'] ?? null,
            'url' => $params['url'] ?? null,
            'x_signature' => $params['x_signature'] ?? null,
        ], $signatureValid);
    }

    private function http()
    {
        $request = Http::withBasicAuth($this->apiKey, '')
            ->withUserAgent($this->userAgent)
            ->acceptJson()
            ->timeout(max(1, $this->timeoutSeconds));

        if ($this->retryTimes > 0) {
            $request = $request->retry($this->retryTimes, max(0, $this->retrySleepMs));
        }

        return $request;
    }

    private function guardApiCredentials(): void
    {
        if (trim($this->apiKey) == '') {
            throw new InvalidArgumentException('Billplz API key is not configured.');
        }
    }

    private function guardCollectionId(): void
    {
        if (trim($this->collectionId) == '') {
            throw new InvalidArgumentException('Billplz collection_id is not configured.');
        }
    }

    private function normalizeMobile(?string $mobile): ?string
    {
        if ($mobile === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $mobile);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Compute and verify an HMAC-SHA256 X-Signature.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $parameters
     */
    private function verifySignature(array $data, array $parameters, string $givenHash): bool
    {
        $parts = [];

        foreach ($parameters as $key) {
            $parts[] = $key.($data[$key] ?? '');
        }

        $expected = hash_hmac('sha256', implode('|', $parts), (string) $this->xSignatureKey);

        return hash_equals($expected, $givenHash);
    }

    /**
     * @param  array<string, mixed>  $billplz
     * @return array<string, mixed>
     */
    private function buildRedirectSignatureData(array $billplz): array
    {
        return [
            'billplzid' => $billplz['id'] ?? '',
            'billplzpaid_at' => $billplz['paid_at'] ?? '',
            'billplzpaid' => $billplz['paid'] ?? '',
            'billplztransaction_id' => $billplz['transaction_id'] ?? '',
            'billplztransaction_status' => $billplz['transaction_status'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildWebhookSignatureData(array $params): array
    {
        return [
            'amount' => $params['amount'] ?? '',
            'collection_id' => $params['collection_id'] ?? '',
            'due_at' => $params['due_at'] ?? '',
            'email' => $params['email'] ?? '',
            'id' => $params['id'] ?? '',
            'mobile' => $params['mobile'] ?? '',
            'name' => $params['name'] ?? '',
            'paid_amount' => $params['paid_amount'] ?? '',
            'paid_at' => $params['paid_at'] ?? '',
            'paid' => $params['paid'] ?? '',
            'state' => $params['state'] ?? '',
            'transaction_id' => $params['transaction_id'] ?? '',
            'transaction_status' => $params['transaction_status'] ?? '',
            'url' => $params['url'] ?? '',
        ];
    }
}
