<?php

use App\Http\Controllers\RagSearchController;
use Illuminate\Support\Facades\Route;

Route::post('/rag/search', [RagSearchController::class, 'search']);
Route::get('/ping', fn() => response()->json(['ok' => true]));
