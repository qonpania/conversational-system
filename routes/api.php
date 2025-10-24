<?php

use App\Http\Controllers\RagSearchController;
use App\Http\Controllers\N8n\InboundController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentPromptController;

Route::post('/rag/search', [RagSearchController::class, 'search']);

Route::get('/ping', fn() => response()->json(['ok' => true]));

Route::post('/n8n/telegram/message', [InboundController::class,'store']);

// Route::middleware('prompt.api')->group(function () {
Route::get('/agents/{slug}/prompt', [AgentPromptController::class, 'showActive']);
// });
