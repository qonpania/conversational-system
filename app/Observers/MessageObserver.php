<?php

namespace App\Observers;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageObserver
{
    public function created(Message $message): void
    {
        try {
            Http::asJson()
                ->timeout(3)->connectTimeout(2)     // no bloquees
                ->post(config('services.n8n.analyze_webhook'), [
                    'message_id'      => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'direction'       => $message->direction, // inbound|outbound
                    'type'            => $message->type,      // text|image|...
                    'text'            => $message->text,
                    'sent_at'         => optional($message->sent_at)->toIso8601String(),
                    // si quieres dar más contexto:
                    //'last_messages'   => [...],
                ]);
        } catch (\Throwable $e) {
            Log::warning('n8n analyze webhook failed: '.$e->getMessage());
        }
    }
}
