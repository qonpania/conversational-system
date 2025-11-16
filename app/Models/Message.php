<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Image\Enums\Fit;


class Message extends Model implements HasMedia
{
    use HasUuids;
    use InteractsWithMedia;
    // protected $fillable = ['conversation_id','direction','type','text','payload','attachments','sent_at'];
    protected $casts = ['payload'=>'array','attachments'=>'array','sent_at'=>'datetime'];

    public function conversation(){
        return $this->belongsTo(Conversation::class);
    }

    public function analytics() {
        return $this->hasOne(MessageAnalytic::class);
    }

    /** Media Library */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('public')               // o 'media' si definiste otro
            ->withResponsiveImages()
            ->acceptsMimeTypes([
                'image/jpeg','image/png','image/webp','image/gif',
                'video/mp4','video/webm','audio/mpeg','audio/ogg','audio/webm',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip','text/plain'
            ])
            ->singleFile(false);
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        // Thumbs para imágenes
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 512, 512)
            ->nonQueued(); // si prefieres inmediato; quítalo si siempre usas queue
    }
}
