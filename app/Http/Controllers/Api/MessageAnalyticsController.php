<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationAnalyticsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAnalytic;
use Illuminate\Http\Request;

class MessageAnalyticsController extends Controller
{
    public function store(Request $request, string $id)
    {
        $msg = Message::with('conversation')->findOrFail($id);

        $data = $request->validate([
            'sentiment'       => 'required|in:positive,neutral,negative',
            'sentiment_score' => 'required|numeric|min:-1|max:1',
            'toxicity_flag'   => 'boolean',
            'abuse_flag'      => 'boolean',
            'pii_flag'        => 'boolean',
            'language'        => 'nullable|string|max:8',
            'intent'          => 'nullable|array',
            'entities'        => 'nullable|array',
        ]);

        MessageAnalytic::updateOrCreate(
            ['message_id' => $msg->id],
            $data
        );

        // (Opcional) también podrías emitir aquí algo por mensaje

        return response()->json(['ok' => true]);
    }
}
