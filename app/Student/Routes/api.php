<?php

use App\Student\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

Route::controller(CertificateController::class)->group(function() {
    Route::get('/students/{code}', 'student');
    Route::post('/certificates/generate', 'generateSingle');
    Route::post('/certificates/bulk-upload', 'uploadBulk');
    Route::get('/certificates/verify/{trackingCode}', 'verify');
});
