<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationStatusController;

Route::prefix('v1')->middleware(['api', 'throttle:60,1'])->group(function () {

    Route::post('/notifications/send', [NotificationController::class, 'send'])
        ->name('notifications.send');

    Route::get('/notifications/recipient/{recipient}', [NotificationStatusController::class, 'byRecipient'])
        ->name('notifications.recipient');

    Route::get('/notifications/{notification}', [NotificationStatusController::class, 'show'])
        ->name('notifications.show');
});

Route::get('/health', [HealthController::class, 'health'])
    ->name('notifications.health');


// Временно для теста
Route::get('/docs', function () {
    return 'Scramble is working';
});

// Swagger UI
Route::get('/docs', function () {
    return view('swagger');
});
