# izzudin96/billplz-laravel

A lightweight Billplz package for Laravel 11/12/13.

## Features

- Create bill
- Get bill
- Verify redirect signature
- Verify webhook signature

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
```

## Usage

```php
use Izzudin96\Billplz\BillplzClient;

$bill = app(BillplzClient::class)->createBill(
    email: 'user@example.com',
    mobile: null,
    name: 'User Name',
    amountCents: 1000,
    callbackUrl: route('billplz-webhook'),
    description: 'Payment',
);
```
