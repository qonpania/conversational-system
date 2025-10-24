<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Contact extends Model
{
    use HasUuids;
    // protected $fillable = ['external_id','username','name','profile'];
    protected $casts = ['profile'=>'array'];

    public function conversations(){
        return $this->hasMany(Conversation::class);
    }
}
