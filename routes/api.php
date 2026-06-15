<?php

use App\Http\Controllers\ClassifyController;
use Illuminate\Support\Facades\Route;

Route::post('/classify', [ClassifyController::class, 'store']);
