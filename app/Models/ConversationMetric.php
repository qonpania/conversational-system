<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMetric extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'conversation_id';

    protected $casts = [
        'top_intents' => 'array',
        'fcr' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
