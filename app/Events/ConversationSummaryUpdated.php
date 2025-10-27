<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ConversationSummaryUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public string $conversationId,
        public string $summary,
        public ?array $meta,
        public ?string $updatedAtIso
    ) {}

    public function broadcastOn(): array
    {
        return [ new PrivateChannel('conversations.' . $this->conversationId) ];
    }

    public function broadcastAs(): string
    {
        return 'summary.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'summary'     => $this->summary,
            'summary_meta'=> $this->meta ?? [],
            'updated_at'  => $this->updatedAtIso,
        ];
    }
}
