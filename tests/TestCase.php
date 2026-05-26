<?php

namespace Tests;

use Izzudin96\Billplz\BillplzServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BillplzServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('billplz', [
            'key' => 'test-key',
            'x-signature' => 'test-signature-key',
            'collection_id' => 'collection-123',
            'sandbox' => true,
            'version' => 'v3',
            'timeout_seconds' => 5,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'user_agent' => 'billplz-laravel-client-test',
        ]);
    }
}
