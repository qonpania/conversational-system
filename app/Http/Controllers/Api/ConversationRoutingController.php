<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Conversation, Message};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConversationRoutingController extends Controller
{
    // n8n pregunta si responde la IA
    public function show(string $id)
    {
        $c = Conversation::with(['assignedUser'])->findOrFail($id);
        return response()->json([
            'routing_mode' => $c->routing_mode,   // ai|human|hybrid
            'assigned_user'=> $c->assignedUser?->only(['id','name','email']),
        ]);
    }

    // Mensaje OUTBOUND escrito por el admin desde Laravel (Filament) → Telegram vía n8n
    public function sendAdminMessage(Request $r, string $id)
    {
        $data = $r->validate([
            'text' => ['required','string','max:4000'],
        ]);

        $c = Conversation::with(['channel','contact'])->findOrFail($id);

        // (A) Enviar a n8n (webhook que hace Telegram SendMessage)
        $url = config('services.n8n.admin_outbound_webhook'); // .env
        abort_if(! $url, 500, 'N8N_ADMIN_OUTBOUND_WEBHOOK no configurado');

        $resp = Http::asJson()->post($url, [
            'conversation_id' => $c->id,
            'channel'         => $c->channel->driver, // "telegram"
            'contact'         => [
                'external_id' => $c->contact->external_id,
                'username'    => $c->contact->username,
                'name'        => $c->contact->name,
            ],
            'text'            => $data['text'],
        ])->throw()->json();

        // (B) Registrar el mensaje localmente como OUTBOUND (humano)
        $externalMsgId = data_get($resp, 'result.message_id');
        $msg = $c->messages()->create([
            'direction'     => 'outbound',
            'type'          => 'text',
            'text'          => $data['text'],
            'payload'       => ['raw'=>['telegram'=>['message_id'=>$externalMsgId]]],
            'attachments'   => null,
            'sent_at'       => now(),
        ]);

        $c->update(['last_message_at' => $msg->sent_at]);

        return response()->json(['ok'=>true,'message_id'=>$msg->id]);
    }
}
