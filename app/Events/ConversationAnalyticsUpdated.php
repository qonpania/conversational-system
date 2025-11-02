<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ConversationAnalyticsUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public string $conversationId, public array $payload = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'analytics.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
