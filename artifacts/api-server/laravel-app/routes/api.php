<?php

use App\Http\Controllers\ScholarshipController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', [ScholarshipController::class, 'health']);
Route::get('/scholarship/dashboard', [ScholarshipController::class, 'dashboard']);
Route::post('/scholarship/capacities', [ScholarshipController::class, 'saveCapacity']);
Route::get('/scholarship/applicants', [ScholarshipController::class, 'applicants']);
Route::post('/scholarship/applicants', [ScholarshipController::class, 'store']);
Route::patch('/scholarship/applicants/{id}', [ScholarshipController::class, 'update'])
    ->whereNumber('id');
