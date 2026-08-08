<?php

use Illuminate\Support\Facades\Route;

Route::prefix('public/v1')->middleware(['api-key', 'throttle:public-api'])->group(function () {
    require __DIR__.'/me.php';
});
