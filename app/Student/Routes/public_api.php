<?php

use App\Student\Controllers\ValidationController;
use Illuminate\Support\Facades\Route;


Route::controller(ValidationController::class)->group(function() {
    Route::post('/public/validate-certificate', 'validateCertificate');
    Route::get('/public/download-certificate/{trackingCode}', 'downloadValidated');
});
