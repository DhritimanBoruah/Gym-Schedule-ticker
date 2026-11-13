<?php

use App\Http\Controllers\FitnessController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/api/fitness', [FitnessController::class, 'index']);
Route::post('/api/logs', [FitnessController::class, 'saveLog']);
Route::post('/api/profile', [FitnessController::class, 'saveProfile']);
Route::post('/api/weights', [FitnessController::class, 'saveWeight']);
