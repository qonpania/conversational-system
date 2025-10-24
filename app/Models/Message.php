<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Message extends Model
{
    use HasUuids;
    // protected $fillable = ['conversation_id','direction','type','text','payload','attachments','sent_at'];
    protected $casts = ['payload'=>'array','attachments'=>'array','sent_at'=>'datetime'];

    public function conversation(){
        return $this->belongsTo(Conversation::class);
    }
}
