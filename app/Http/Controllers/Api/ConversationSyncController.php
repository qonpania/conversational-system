<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationSyncController extends Controller
{
    public function messages(Request $request, string $id)
    {
        $limit = min((int) $request->query('limit', 50), 200);
        $conv = Conversation::query()
            ->with(['messages' => fn($q) => $q->orderBy('sent_at','desc')->limit($limit)])
            ->with(['contact','channel'])
            ->findOrFail($id);

        $items = $conv->messages->sortBy('sent_at')->values()->map(fn($m) => [
            'id'        => $m->id,
            'direction' => $m->direction,
            'type'      => $m->type,
            'text'      => $m->text,
            'sent_at'   => optional($m->sent_at)->toIso8601String(),
        ]);

        return response()->json([
            'conversation' => [
                'id'      => $conv->id,
                'status'  => $conv->status,
                'contact' => [
                    'id' => $conv->contact->id,
                    'name' => $conv->contact->name,
                    'username' => $conv->contact->username,
                ],
                'channel' => [ 'driver' => $conv->channel->driver, 'name' => $conv->channel->name ],
            ],
            'messages' => $items,
        ]);
    }

    public function storeSummary(Request $request, string $id)
    {
        $data = $request->validate([
            'summary' => ['required','string'],
            'meta'    => ['nullable','array'], // {model, tokens, digest, updated_by}
        ]);

        // dd('debug storeSummary', $data);
        $conv = Conversation::findOrFail($id);
        $conv->forceFill([
            'summary'            => $data['summary'],
            'summary_meta'       => $data['meta'] ?? [],
            'summary_updated_at' => now(),
        ])->save();

        return response()->json(['ok' => true, 'updated_at' => $conv->summary_updated_at?->toIso8601String()]);
    }
}
