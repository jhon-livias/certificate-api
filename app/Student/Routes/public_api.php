<?php

use App\Student\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

Route::get('/verify/{tracking_code}', [CertificateController::class, 'verify']);
