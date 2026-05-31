<?php

namespace Izzudin96\Billplz\Facades;

use Izzudin96\Billplz\BillplzClient;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin BillplzClient
 */
class Billplz extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BillplzClient::class;
    }
}
