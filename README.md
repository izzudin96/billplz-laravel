# izzudin96/billplz-laravel

A lightweight Billplz package for Laravel 11/12/13.

It provides a small Billplz client for common payment flow tasks:

- create bills
- fetch bill status
- verify callback/webhook signatures (strict mode)
- parse callback/webhook payloads (best-effort mode)
- configurable HTTP timeout/retry behavior
- safe input/config guards with clear exceptions

## Features

- Create bill
- Get bill
- Verify redirect signature
- Verify webhook signature
- Best-effort redirect parsing
- Best-effort webhook parsing

## When to use which method

- `verifyRedirect()` and `verifyWebhook()`:
    Use when you want hard security checks and prefer exceptions on invalid signatures.
- `parseRedirect()`:
    Use when redirect/callback is for UX only and webhook is your source of truth.
- `parseWebhook()`:
    Use when you want null-on-failure behavior instead of exceptions.

## Installation

```bash
composer require izzudin96/billplz-laravel
```

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag=billplz-config
```

Or set env variables directly:

```env
BILLPLZ_API_KEY=
BILLPLZ_X_SIGNATURE=
BILLPLZ_COLLECTION_ID=
BILLPLZ_VERSION=v3
BILLPLZ_SANDBOX=false
BILLPLZ_TIMEOUT_SECONDS=10
BILLPLZ_RETRY_TIMES=1
BILLPLZ_RETRY_SLEEP_MS=200
BILLPLZ_USER_AGENT=billplz-laravel-client
```

Config supports both `x-signature` and `x_signature` keys for compatibility.

Optional `config/services.php` style usage is also supported:

```php
return [
    'billplz' => [
        'key' => env('BILLPLZ_API_KEY'),
        'x_signature' => env('BILLPLZ_X_SIGNATURE'),
        'collection_id' => env('BILLPLZ_COLLECTION_ID'),
        'sandbox' => env('BILLPLZ_SANDBOX', false),
        'version' => env('BILLPLZ_VERSION', 'v3'),
        'timeout_seconds' => env('BILLPLZ_TIMEOUT_SECONDS', 10),
        'retry_times' => env('BILLPLZ_RETRY_TIMES', 1),
        'retry_sleep_ms' => env('BILLPLZ_RETRY_SLEEP_MS', 200),
        'user_agent' => env('BILLPLZ_USER_AGENT', 'billplz-laravel-client'),
    ],
];
```

### HTTP behavior defaults

- Timeout: 10 seconds
- Retries: 1
- Retry backoff: 200ms

This applies to bill create/get requests.

## Usage

### How BillplzClient is resolved in Laravel

`BillplzClient::class` is only a class name string. To call client methods, you need an instance resolved by Laravel's service container.

Preferred patterns:

1. Method injection (clean and explicit)

```php
use Izzudin96\Billplz\BillplzClient;

public function show(string $billId, BillplzClient $billplz)
{
    $bill = $billplz->getBill($billId);
}
```

1. Constructor injection (good when used in multiple methods)

```php
use Izzudin96\Billplz\BillplzClient;

class PaymentController extends Controller
{
    public function __construct(private BillplzClient $billplz)
    {
    }

    public function show(string $billId)
    {
        $bill = $this->billplz->getBill($billId);
    }
}
```

1. Facade usage (shortest call style)

```php
use Izzudin96\Billplz\Facades\Billplz;

$bill = Billplz::getBill($billId);
```

1. app helper (works, but usually less preferred than DI)

```php
use Izzudin96\Billplz\BillplzClient;

$bill = app(BillplzClient::class)->getBill($billId);
```

Why DI/facade is friendlier:

- Better readability in controllers/services
- Easier testing and mocking
- No repeated container lookup calls

### 1) Create a bill

```php
use Izzudin96\Billplz\BillplzClient;

$bill = app(BillplzClient::class)->createBill(
    email: 'user@example.com',
    mobile: '60123456789',
    name: 'User Name',
    amountCents: 25900, // RM259.00 in cents/sen
    callbackUrl: route('payments.billplz.webhook'),
    description: 'Booking BK-10021',
    optional: [
        'redirect_url' => route('payments.billplz.callback'),
        'reference_1_label' => 'Booking Ref',
        'reference_1' => 'BK-10021',
        'reference_2_label' => 'Customer ID',
        'reference_2' => 'CUS-890',
        // Additional Billplz-supported fields can be passed through here.
        // Required fields from method params always take precedence.
    ],
);

// Save for reconciliation and redirect user to Billplz page
$billId = $bill['id'];
$paymentUrl = $bill['url'];
```

### 2) Get bill status

```php
use Izzudin96\Billplz\BillplzClient;

$bill = app(BillplzClient::class)->getBill($billId);

// Examples of useful fields from Billplz response:
// $bill['paid']
// $bill['paid_at']
// $bill['state']
```

### 3) Strict signature verification

Use this when you want invalid signature payloads to fail immediately.

```php
use Izzudin96\Billplz\BillplzClient;
use Izzudin96\Billplz\Exceptions\FailedSignatureVerification;

try {
    $redirect = app(BillplzClient::class)->verifyRedirect(request()->query());
    $webhook = app(BillplzClient::class)->verifyWebhook(request()->all());
} catch (FailedSignatureVerification $e) {
    report($e);
    abort(403, 'Invalid Billplz signature');
}
```

### 4) Best-effort redirect (recommended for UX callback)

Use this when callback/redirect is only for showing payment result to user.
Process fulfillment from webhook instead.

```php
use Izzudin96\Billplz\BillplzClient;

$redirect = app(BillplzClient::class)->parseRedirect(request()->query());

if ($redirect === null) {
    return redirect()->route('payments.failed')
        ->with('message', 'Payment information is incomplete.');
}

// signature_valid can be true, false, or null (when signature key not configured)
$isPaid = (bool) ($redirect['paid'] ?? false);

return redirect()->route('payments.result')->with([
    'bill_id' => $redirect['id'] ?? null,
    'paid' => $isPaid,
    'signature_valid' => $redirect['signature_valid'],
]);
```

### 5) Best-effort webhook parser

Use this when you prefer null checks over exception handling.

```php
use Izzudin96\Billplz\BillplzClient;

$payload = app(BillplzClient::class)->parseWebhook(request()->all());

if ($payload === null) {
    return response()->json(['message' => 'Invalid payload'], 403);
}

if (($payload['paid'] ?? false) === true) {
    // Mark order/booking as paid
}

return response()->json(['ok' => true]);
```

## End-to-end controller example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Izzudin96\Billplz\BillplzClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillplzPaymentController extends Controller
{
    public function checkout(Order $order, BillplzClient $billplz): RedirectResponse
    {
        $bill = $billplz->createBill(
            email: $order->customer_email,
            mobile: $order->customer_mobile,
            name: $order->customer_name,
            amountCents: (int) round($order->total * 100),
            callbackUrl: route('payments.billplz.webhook'),
            description: 'Order '.$order->reference,
            optional: [
                'redirect_url' => route('payments.billplz.callback'),
                'reference_1_label' => 'Order',
                'reference_1' => $order->reference,
            ],
        );

        $order->update([
            'billplz_bill_id' => $bill['id'],
            'payment_url' => $bill['url'],
        ]);

        return redirect()->away($bill['url']);
    }

    public function callback(Request $request, BillplzClient $billplz): RedirectResponse
    {
        $redirect = $billplz->parseRedirect($request->query());

        if ($redirect === null) {
            return redirect()->route('orders.index')
                ->with('error', 'Unable to read payment callback.');
        }

        return redirect()->route('orders.index')->with('status',
            ($redirect['paid'] ?? false)
                ? 'Payment received. Waiting confirmation.'
                : 'Payment not completed.'
        );
    }

    public function webhook(Request $request, BillplzClient $billplz): JsonResponse
    {
        $payload = $billplz->parseWebhook($request->all());

        if ($payload === null) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $order = Order::where('billplz_bill_id', $payload['id'] ?? '')->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (($payload['paid'] ?? false) === true) {
            $order->update([
                'payment_status' => 'paid',
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
```

## Suggested routes

```php
use App\Http\Controllers\BillplzPaymentController;

Route::post('/payments/{order}/checkout', [BillplzPaymentController::class, 'checkout'])
    ->name('payments.billplz.checkout');

Route::get('/payments/billplz/callback', [BillplzPaymentController::class, 'callback'])
    ->name('payments.billplz.callback');

Route::post('/payments/billplz/webhook', [BillplzPaymentController::class, 'webhook'])
    ->name('payments.billplz.webhook');
```

## Use cases

- Marketplace checkout: create bill per order and reconcile payment on webhook.
- Booking system: attach booking reference via `reference_1` and mark booking paid after webhook.
- Membership/fees: use best-effort redirect for user messaging, strict webhook for state changes.
- Legacy migration: keep old callback behavior with `parseRedirect()`, then gradually move to strict verification.

## Notes

- Amount is in cents/sen. Example: RM12.34 = `1234`.
- Prefer webhook as the authoritative source for payment completion.
- Redirect callback can be delayed, interrupted, or tampered; treat it as user-facing signal only.
- If `BILLPLZ_X_SIGNATURE` is not configured, signature checks are skipped and `signature_valid` is `null` in parse methods.
- `createBill()` throws `InvalidArgumentException` when required config or inputs are missing/invalid.
    Example checks: empty API key, empty collection ID, invalid email, amount <= 0.

## Testing

The package includes PHPUnit + Orchestra Testbench tests.

Run tests locally:

```bash
composer install
composer test
```

Current test coverage includes:

- `createBill()` request payload and response handling.
- Input validation exceptions for invalid payloads.
- Strict signature verification failure behavior.
- Best-effort parsing behavior for redirect and webhook.
- URL encoding behavior in `getBill()`.

## GitHub Actions CI

CI workflow is available at `.github/workflows/tests.yml` and runs on:

- push to `main`
- pull requests

Matrix:

- PHP 8.2
- PHP 8.3
- PHP 8.4

Pipeline steps:

- `composer validate --strict`
- `composer install`
- `composer test`
