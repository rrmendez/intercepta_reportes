<?php

use App\Http\Controllers\DevPdfSamplePreviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/dev/pdf-sample', DevPdfSamplePreviewController::class)->name('dev.pdf-sample');
