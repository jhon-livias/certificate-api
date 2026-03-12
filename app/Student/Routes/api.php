<?php

use App\Student\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

Route::controller(CertificateController::class)->group(function() {
    Route::get('/students', 'students');
    Route::get('/students/{code}', 'student');
    Route::post('/students/bulk-upload', 'uploadBulk');
    Route::get('/certificates/verify/{trackingCode}', 'verify');
    Route::post('/certificates/generate', 'generateSingle');
});
