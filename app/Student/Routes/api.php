<?php

use App\Student\Controllers\CertificateController;
use App\Student\Controllers\GenerateCertificateController;
use App\Student\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::controller(StudentController::class)->group(function() {
    Route::get('/students', 'students');
    Route::get('/students/{code}', 'student');
    Route::post('/students/bulk-upload', 'uploadBulk');
});

Route::controller(CertificateController::class)->group(function() {
    Route::post('/certificates', 'store');
    Route::post('/certificates/{certificate}', 'update');
    Route::delete('/certificates/{certificate}', 'destroy');
    Route::get('/certificates/{certificate}/download', 'download');
    Route::get('/certificates/{certificate}/preview', 'preview');
    Route::get('/certificates/{certificate}/next-code', 'nextCode');
    Route::get('/certificates', 'index');
});

Route::controller(GenerateCertificateController::class)->group(function() {
    Route::post('/generate-certificate', 'generate');
    Route::post('/certificates/{issuedCertificate}/send-email', 'sendEmail');
    Route::get('/certificates/download-generated/{issuedCertificate}', 'downloadGenerated');
});
