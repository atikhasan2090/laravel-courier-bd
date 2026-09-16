<?php

use Illuminate\Support\Facades\Route;
use Shipkit\CourierBD\Http\Controllers\WebhookController;

$prefix = config('shipkit.webhooks.prefix', 'shipkit/webhooks');
$middleware = config('shipkit.webhooks.middleware', ['api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    Route::post('/{courier}', [WebhookController::class, 'handle'])->name('shipkit.webhooks.handle');
});
