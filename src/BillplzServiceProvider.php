<?php

namespace Izzudin96\Billplz;

use Illuminate\Support\ServiceProvider;

class BillplzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/billplz.php', 'billplz');

        $this->app->singleton(BillplzClient::class, function () {
            $config = array_merge(
                config('billplz', []),
                config('services.billplz', []),
            );

            return new BillplzClient(
                apiKey: (string) ($config['key'] ?? ''),
                xSignatureKey: $config['x-signature'] ?? $config['x_signature'] ?? null,
                collectionId: (string) ($config['collection_id'] ?? ''),
                sandbox: (bool) ($config['sandbox'] ?? false),
                version: (string) ($config['version'] ?? 'v3'),
                timeoutSeconds: (int) ($config['timeout_seconds'] ?? 10),
                retryTimes: (int) ($config['retry_times'] ?? 1),
                retrySleepMs: (int) ($config['retry_sleep_ms'] ?? 200),
                userAgent: (string) ($config['user_agent'] ?? 'billplz-laravel-client'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/billplz.php' => config_path('billplz.php'),
        ], 'billplz-config');
    }
}
