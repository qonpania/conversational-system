<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Message;

class MessageAnalytic extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'intent' => 'array',
        'entities' => 'array',
        'toxicity_flag' => 'boolean',
        'abuse_flag' => 'boolean',
        'pii_flag' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            $m->id ??= (string) Str::uuid();
        });
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
