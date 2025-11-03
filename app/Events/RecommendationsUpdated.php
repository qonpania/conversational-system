<?php

// app/Events/RecommendationsUpdated.php
namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class RecommendationsUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn()
    {
        return new PrivateChannel('conversations.' . $this->conversation->id);
    }

    public function broadcastAs()
    {
        return 'recommendations.updated';
    }

    public function broadcastWith()
    {
        return [
            'recommendations' => $this->conversation->recommendations,
            'recommendations_meta' => $this->conversation->recommendations_meta,
            'updated_at' => optional($this->conversation->recommendations_updated_at)->toIso8601String(),
        ];
    }
}
