<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Channel extends Model
{
    use HasUuids;
    // protected $fillable = ['driver','name','settings'];
    protected $casts = ['settings'=>'array'];

    public function conversations(){
        return $this->hasMany(Conversation::class);
    }
}
