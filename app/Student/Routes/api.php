<?php

use App\Student\Controllers\CertificateController;
use App\Student\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::controller(StudentController::class)->group(function() {
    Route::get('/students', 'students');
    Route::get('/students/{code}', 'student');
    Route::post('/students/bulk-upload', 'uploadBulk');
});

Route::controller(CertificateController::class)->group(function() {
    Route::get('/certificates/verify/{trackingCode}', 'verify');
    Route::post('/certificates/generate', 'generateSingle');
    Route::post('/certificates', 'store');
    Route::post('/certificates/{certificate}', 'update');
    Route::get('/certificates', 'index');
    Route::get('/certificates/{certificate}/download', 'download');
});
