<?php

namespace Tests;

use Izzudin96\Billplz\BillplzClient;
use Izzudin96\Billplz\Exceptions\FailedSignatureVerification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class BillplzClientTest extends TestCase
{
    public function test_create_bill_sends_expected_payload_and_returns_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'bill-123',
                'url' => 'https://billplz.test/bills/bill-123',
            ], 200),
        ]);

        $client = app(BillplzClient::class);

        $result = $client->createBill(
            email: 'user@example.com',
            mobile: ' 6012-345 678 ',
            name: 'User Name',
            amountCents: 25900,
            callbackUrl: 'https://example.com/webhook',
            description: 'Order REF-001',
            optional: [
                'redirect_url' => 'https://example.com/callback',
                'reference_1_label' => 'Order',
                'reference_1' => 'REF-001',
                'due_at' => '2026-06-01',
                'collection_id' => 'should-not-override',
            ]
        );

        $this->assertSame('bill-123', $result->id);
        $this->assertSame('https://billplz.test/bills/bill-123', $result->url);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://www.billplz-sandbox.com/api/v3/bills'
                && $request['collection_id'] === 'collection-123'
                && $request['mobile'] === '6012345678'
                && $request['due_at'] === '2026-06-01';
        });
    }

    public function test_create_bill_throws_for_invalid_input(): void
    {
        $client = app(BillplzClient::class);

        $this->expectException(InvalidArgumentException::class);
        $client->createBill(
            email: 'not-an-email',
            mobile: null,
            name: 'User Name',
            amountCents: 100,
            callbackUrl: 'https://example.com/webhook',
            description: 'Invalid email test'
        );
    }

    public function test_verify_redirect_throws_for_invalid_signature(): void
    {
        $client = app(BillplzClient::class);

        $this->expectException(FailedSignatureVerification::class);

        $client->verifyRedirect([
            'billplz' => [
                'id' => 'bill-1',
                'paid_at' => '2026-01-01 00:00:00',
                'paid' => 'true',
                'transaction_id' => 'txn-1',
                'transaction_status' => 'completed',
                'x_signature' => 'invalid-signature',
            ],
        ]);
    }

    public function test_verify_redirect_returns_payload_when_signature_is_valid(): void
    {
        $client = app(BillplzClient::class);

        $billplz = [
            'id' => 'bill-1',
            'paid_at' => '2026-01-01 00:00:00',
            'paid' => 'true',
            'transaction_id' => 'txn-1',
            'transaction_status' => 'completed',
        ];

        $result = $client->verifyRedirect([
            'billplz' => $billplz + [
                'x_signature' => $this->makeRedirectSignature($billplz),
            ],
        ]);

        $this->assertSame('bill-1', $result->id);
        $this->assertTrue($result->paid);
        $this->assertTrue($result->signature_valid);
    }

    public function test_parse_redirect_returns_payload_with_false_signature_status(): void
    {
        $client = app(BillplzClient::class);

        $result = $client->parseRedirect([
            'billplz' => [
                'id' => 'bill-1',
                'paid' => '1',
                'x_signature' => 'invalid-signature',
            ],
        ]);

        $this->assertNotNull($result);
        $this->assertSame('bill-1', $result->id);
        $this->assertTrue($result->paid);
        $this->assertFalse($result->signature_valid);
    }

    public function test_parse_webhook_returns_null_for_invalid_signature(): void
    {
        $client = app(BillplzClient::class);

        $result = $client->parseWebhook([
            'id' => 'bill-1',
            'amount' => '100',
            'collection_id' => 'collection-123',
            'paid' => 'true',
            'x_signature' => 'invalid-signature',
        ]);

        $this->assertNull($result);
    }

    public function test_verify_webhook_returns_payload_when_signature_is_valid(): void
    {
        $client = app(BillplzClient::class);

        $webhook = [
            'id' => 'bill-1',
            'amount' => '100',
            'collection_id' => 'collection-123',
            'paid' => 'true',
            'paid_at' => '2026-01-01 00:00:00',
            'state' => 'paid',
            'transaction_id' => 'txn-1',
            'transaction_status' => 'completed',
            'url' => 'https://billplz.test/bills/bill-1',
        ];

        $result = $client->verifyWebhook($webhook + [
            'x_signature' => $this->makeWebhookSignature($webhook),
        ]);

        $this->assertSame('bill-1', $result->id);
        $this->assertSame('100', $result->amount);
        $this->assertTrue($result->paid);
        $this->assertTrue($result->signature_valid);
    }

    public function test_get_bill_uses_encoded_bill_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'bill/123',
                'paid' => false,
            ], 200),
        ]);

        $client = app(BillplzClient::class);
        $client->getBill('bill/123');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://www.billplz-sandbox.com/api/v3/bills/bill%2F123';
        });
    }

    private function makeRedirectSignature(array $billplz): string
    {
        $parts = [
            'billplzid'.($billplz['id'] ?? ''),
            'billplzpaid_at'.($billplz['paid_at'] ?? ''),
            'billplzpaid'.($billplz['paid'] ?? ''),
            'billplztransaction_id'.($billplz['transaction_id'] ?? ''),
            'billplztransaction_status'.($billplz['transaction_status'] ?? ''),
        ];

        return hash_hmac('sha256', implode('|', $parts), 'test-signature-key');
    }

    private function makeWebhookSignature(array $webhook): string
    {
        $parts = [
            'amount'.($webhook['amount'] ?? ''),
            'collection_id'.($webhook['collection_id'] ?? ''),
            'due_at'.($webhook['due_at'] ?? ''),
            'email'.($webhook['email'] ?? ''),
            'id'.($webhook['id'] ?? ''),
            'mobile'.($webhook['mobile'] ?? ''),
            'name'.($webhook['name'] ?? ''),
            'paid_amount'.($webhook['paid_amount'] ?? ''),
            'paid_at'.($webhook['paid_at'] ?? ''),
            'paid'.($webhook['paid'] ?? ''),
            'state'.($webhook['state'] ?? ''),
            'transaction_id'.($webhook['transaction_id'] ?? ''),
            'transaction_status'.($webhook['transaction_status'] ?? ''),
            'url'.($webhook['url'] ?? ''),
        ];

        return hash_hmac('sha256', implode('|', $parts), 'test-signature-key');
    }
}
