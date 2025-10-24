<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Agent extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    // protected $fillable = ['slug','name','description','meta'];
    protected $casts = ['meta'=>'array'];

    public function prompts() {
        return $this->hasMany(AgentPromptVersion::class);
    }
    
    public function activePrompt() {
        return $this->hasOne(AgentPromptVersion::class)->where('is_active', true);
    }
}
