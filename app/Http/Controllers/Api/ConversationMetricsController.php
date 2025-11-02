<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationAnalyticsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMetric;
use Illuminate\Http\Request;

class ConversationMetricsController extends Controller
{
    public function store(Request $request, string $id)
    {
        $conv = Conversation::findOrFail($id);

        $data = $request->validate([
            'sentiment_overall'  => 'required|in:positive,neutral,negative',
            'sentiment_score'    => 'required|numeric|min:-1|max:1',
            'sentiment_trend'    => 'nullable|in:up,down,flat',
            'message_count'      => 'nullable|integer|min:0',
            'handover_count'     => 'nullable|integer|min:0',
            'first_response_time'=> 'nullable|integer|min:0',
            'avg_response_time'  => 'nullable|integer|min:0',
            'fcr'                => 'nullable|boolean',
            'csat_pred'          => 'nullable|numeric|min:0|max:1',
            'churn_risk'         => 'nullable|numeric|min:0|max:1',
            'top_intents'        => 'nullable|array',
        ]);

        ConversationMetric::updateOrCreate(
            ['conversation_id' => $conv->id],
            $data
        );

        // Broadcast para refrescar widgets/pills
        event(new ConversationAnalyticsUpdated($conv->id, [
            'metrics' => $data,
        ]));

        return response()->json(['ok' => true]);
    }
}
