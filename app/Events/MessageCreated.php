<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageCreated implements ShouldBroadcastNow
{
    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [ new PrivateChannel('conversations.' . $this->message->conversation_id) ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => (string) $this->message->id,
            'direction' => $this->message->direction,
            'type'      => $this->message->type,
            'text'      => $this->message->text,
            'sent_at'   => optional($this->message->sent_at)->toIso8601String(),
        ];
    }
}
