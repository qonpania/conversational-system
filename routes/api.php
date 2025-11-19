<?php

use App\Http\Controllers\RagSearchController;
use App\Http\Controllers\N8n\InboundController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentPromptController;
use App\Http\Controllers\Api\ConversationSyncController;
use App\Http\Controllers\Api\ConversationRoutingController;
use App\Http\Controllers\Api\MessageAnalyticsController;
use App\Http\Controllers\Api\ConversationMetricsController;
use App\Http\Controllers\Api\ConversationRecommendationController;
use App\Http\Controllers\Api\CoverageController;

Route::post('/rag/search', [RagSearchController::class, 'search']);

Route::get('/ping', fn() => response()->json(['ok' => true]));

Route::post('/n8n/telegram/message', [InboundController::class,'store']);

// Route::middleware('prompt.api')->group(function () {
Route::get('/agents/{slug}/prompt', [AgentPromptController::class, 'showActive']);
// });

// n8n lee últimos N mensajes para resumir
Route::get('/conversations/{id}/messages', [ConversationSyncController::class, 'messages'])->name('api.conversation.messages');

// n8n escribe/actualiza el resumen
Route::post('/conversations/{id}/summary', [ConversationSyncController::class, 'storeSummary']);

Route::get('/conversations/{id}/routing', [ConversationRoutingController::class,'show']);
Route::post(
    '/conversations/{id}/outbound/admin',
    [ConversationRoutingController::class, 'sendAdminMessage']
)->name('api.conversation.admin.outbound');

Route::post('/messages/{id}/analytics',     [MessageAnalyticsController::class,'store']);
Route::post('/conversations/{id}/metrics',  [ConversationMetricsController::class,'store']);

Route::post('/conversations/{conversation}/recommendations', [ConversationRecommendationController::class, 'store']);

Route::post('/coverage/check', [CoverageController::class, 'check']);
Route::get('/coverage/locations', [CoverageController::class, 'locations']);
