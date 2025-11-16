<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Conversation extends Model
{
    use HasUuids;
    // protected $fillable = ['channel_id','contact_id','status','last_message_at','meta'];
    protected $casts = [
        'last_message_at'     => 'datetime',
        'summary_updated_at'  => 'datetime',
        'meta'                => 'array',
        'summary_meta'        => 'array',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'handover_at'        => 'datetime',
        'resume_ai_at'      => 'datetime',
        'recommendations_updated_at' => 'datetime',
    ];

    public function channel(){
        return $this->belongsTo(Channel::class);
    }

    public function contact(){
        return $this->belongsTo(Contact::class);
    }

    public function messages(){
        return $this->hasMany(Message::class)->orderBy('sent_at');
    }

    public function assignedUser(){
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function metrics() {
        return $this->hasOne(ConversationMetric::class, 'conversation_id');
    }
}
