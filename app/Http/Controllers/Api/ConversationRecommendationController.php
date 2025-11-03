<?php

namespace App\Http\Controllers\Api;

use App\Events\RecommendationsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationRecommendationController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'recommendations_markdown' => ['required','string'],
            'key_points'         => ['nullable','array'],
            'next_best_actions'  => ['nullable','array'],
            'objections'         => ['nullable','array'],
            'upsell'             => ['nullable','array'],
            'meta'               => ['nullable','array'],
        ]);

        $conversation->fill([
            'recommendations'            => $data['recommendations_markdown'],
            'recommendations_meta'       => [
                'key_points'        => $data['key_points'] ?? [],
                'next_best_actions' => $data['next_best_actions'] ?? [],
                'objections'        => $data['objections'] ?? [],
                'upsell'            => $data['upsell'] ?? [],
                'meta'              => $data['meta'] ?? [],
            ],
            'recommendations_updated_at' => now(),
        ])->save();

        broadcast(new RecommendationsUpdated($conversation));

        return response()->json(['ok' => true]);
    }
}
