<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Conversation extends Model
{
    use HasUuids;
    // protected $fillable = ['channel_id','contact_id','status','last_message_at','meta'];
    protected $casts = ['last_message_at'=>'datetime','meta'=>'array'];

    public function channel(){
        return $this->belongsTo(Channel::class);
    }
    public function contact(){
        return $this->belongsTo(Contact::class);
    }
    public function messages(){
        return $this->hasMany(Message::class)->orderBy('sent_at');
    }
}
