<?php

namespace Izzudin96\Billplz;

use Izzudin96\Billplz\Exceptions\FailedSignatureVerification;
use Illuminate\Support\Facades\Http;

/**
 * Lightweight BillPlz API client.
 *
 * API reference: https://www.billplz.com/api
 */
class BillplzClient
{
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
        string $version = 'v3'
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
     * @return array<string, mixed>
     *
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
    ): array {
        $payload = array_filter([
            'collection_id' => $this->collectionId,
            'email' => $email,
            'mobile' => $mobile,
            'name' => $name,
            'amount' => $amountCents,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'redirect_url' => $optional['redirect_url'] ?? null,
            'reference_1_label' => $optional['reference_1_label'] ?? null,
            'reference_1' => isset($optional['reference_1']) ? (string) $optional['reference_1'] : null,
            'reference_2_label' => $optional['reference_2_label'] ?? null,
            'reference_2' => isset($optional['reference_2']) ? (string) $optional['reference_2'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        return Http::withBasicAuth($this->apiKey, '')
            ->asForm()
            ->post("{$this->baseUrl}/bills", $payload)
            ->throw()
            ->json();
    }

    /**
     * Retrieve an existing bill from BillPlz.
     *
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function getBill(string $billId): array
    {
        return Http::withBasicAuth($this->apiKey, '')
            ->get("{$this->baseUrl}/bills/{$billId}")
            ->throw()
            ->json();
    }

    /**
     * Parse and verify the signed redirect callback from BillPlz.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws FailedSignatureVerification
     */
    public function verifyRedirect(array $params): array
    {
        $billplz = $params['billplz'] ?? null;

        if (! is_array($billplz)) {
            throw new FailedSignatureVerification('Missing billplz parameters in redirect.');
        }

        if ($this->xSignatureKey !== null && ! isset($billplz['x_signature'])) {
            throw new FailedSignatureVerification('Missing billplz x_signature in redirect.');
        }

        if ($this->xSignatureKey !== null) {
            $flat = [
                'billplzid' => $billplz['id'] ?? '',
                'billplzpaid_at' => $billplz['paid_at'] ?? '',
                'billplzpaid' => $billplz['paid'] ?? '',
                'billplztransaction_id' => $billplz['transaction_id'] ?? '',
                'billplztransaction_status' => $billplz['transaction_status'] ?? '',
                'x_signature' => $billplz['x_signature'],
            ];

            if (! $this->verifySignature($flat, self::REDIRECT_PARAMETERS, $billplz['x_signature'])) {
                throw new FailedSignatureVerification;
            }
        }

        return array_merge($billplz, [
            'paid' => ($billplz['paid'] ?? 'false') === 'true',
        ]);
    }

    /**
     * Best-effort redirect parser.
     *
     * Returns null only when required redirect fields are missing.
     * Signature validity is exposed as `signature_valid`.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function parseRedirect(array $params): ?array
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
                $flat = [
                    'billplzid' => $billplz['id'] ?? '',
                    'billplzpaid_at' => $billplz['paid_at'] ?? '',
                    'billplzpaid' => $billplz['paid'] ?? '',
                    'billplztransaction_id' => $billplz['transaction_id'] ?? '',
                    'billplztransaction_status' => $billplz['transaction_status'] ?? '',
                    'x_signature' => $billplz['x_signature'],
                ];

                $signatureValid = $this->verifySignature($flat, self::REDIRECT_PARAMETERS, (string) $billplz['x_signature']);
            }
        }

        return array_merge($billplz, [
            'paid' => ($billplz['paid'] ?? 'false') === 'true',
            'signature_valid' => $signatureValid,
        ]);
    }

    /**
     * Parse and verify the signed webhook POST from BillPlz.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws FailedSignatureVerification
     */
    public function verifyWebhook(array $params): array
    {
        if ($this->xSignatureKey !== null && ! isset($params['x_signature'])) {
            throw new FailedSignatureVerification('Missing x_signature in webhook payload.');
        }

        if ($this->xSignatureKey !== null && ! $this->verifySignature($params, self::WEBHOOK_PARAMETERS, $params['x_signature'])) {
            throw new FailedSignatureVerification;
        }

        return array_merge($params, [
            'paid' => ($params['paid'] ?? 'false') === 'true',
        ]);
    }

    /**
     * Best-effort webhook parser.
     *
     * Webhook should remain authoritative, so this returns null when signature
     * is configured but missing/invalid.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function parseWebhook(array $params): ?array
    {
        if (! isset($params['id'])) {
            return null;
        }

        $signatureValid = null;

        if ($this->xSignatureKey !== null) {
            if (! isset($params['x_signature'])) {
                return null;
            }

            $signatureValid = $this->verifySignature($params, self::WEBHOOK_PARAMETERS, (string) $params['x_signature']);

            if (! $signatureValid) {
                return null;
            }
        }

        return array_merge($params, [
            'paid' => ($params['paid'] ?? 'false') === 'true',
            'signature_valid' => $signatureValid,
        ]);
    }

    /**
     * Compute and verify an HMAC-SHA256 X-Signature.
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
}
