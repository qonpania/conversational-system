<?php

namespace App\Http\Controllers\N8n;

use App\Http\Controllers\Controller;
use App\Models\{Channel, Contact, Conversation, Message};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InboundController extends Controller
{
    public function store(Request $req)
    {
        $data = $req->validate([
            'channel'                 => ['required','string'],         // "telegram"
            'channel_name'            => ['nullable','string'],         // "@tu_bot"
            'contact.external_id'     => ['required','string'],
            'contact.username'        => ['nullable','string'],
            'contact.name'            => ['nullable','string'],
            'message.direction'       => ['required', Rule::in(['inbound','outbound'])],
            'message.type'            => ['required', Rule::in(['text','photo','video','file','voice'])],
            'message.text'            => ['nullable','string'],
            'message.sent_at'         => ['required','date'],
            'message.payload'         => ['nullable','array'],
            'message.attachments'     => ['nullable','array'], // [{url,mime,size,filename}]
        ]);

        // Channel (upsert)
        $channel = Channel::firstOrCreate(
            ['driver' => $data['channel'], 'name' => $data['channel_name'] ?? $data['channel']],
            ['settings' => []]
        );

        // Contact (upsert)
        $c = $data['contact'];
        $contact = Contact::updateOrCreate(
            ['external_id' => $c['external_id']],
            ['username' => $c['username'] ?? null, 'name' => $c['name'] ?? null]
        );

        // Conversation (1 por contact+channel)
        $conversation = Conversation::firstOrCreate(
            ['channel_id' => $channel->id, 'contact_id' => $contact->id],
            ['status' => 'open']
        );

        // Message
        $m = $data['message'];
        $msg = new Message([
            'direction'   => $m['direction'],
            'type'        => $m['type'],
            'text'        => $m['text'] ?? null,
            'payload'     => $m['payload'] ?? null,
            'attachments' => $m['attachments'] ?? null,
            'sent_at'     => $m['sent_at'],
        ]);
        $conversation->messages()->save($msg);

        // Actualiza “last_message_at”
        $conversation->update(['last_message_at' => $msg->sent_at]);

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'message_id' => $msg->id,
        ]);
    }
}
