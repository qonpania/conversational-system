<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB;

class AgentPromptVersion extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    // protected $fillable = [
    //     'agent_id','version','title','content','parameters','status','is_active','activated_at','checksum','notes'
    // ];
    protected $casts = [
        'parameters'=>'array',
        'is_active'=>'boolean',
        'activated_at'=>'datetime',
    ];

    public function agent(){ return $this->belongsTo(Agent::class); }

    // Auto-versionado: siguiente número por agente
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->version)) {
                $max = static::where('agent_id', $m->agent_id)->max('version');
                $m->version = ($max ?? 0) + 1;
            }
            $m->checksum = hash('sha256', ($m->content ?? '').'|'.json_encode($m->parameters ?? []));
        });
    }

    // Activar una versión de forma atómica
    public function activate(): void
    {
        DB::transaction(function () {
            static::where('agent_id', $this->agent_id)->where('is_active', true)
                ->update(['is_active' => false]);
            $this->is_active = true;
            $this->status = 'published';
            $this->activated_at = now();
            $this->save();

            // cache warm
            cache()->forget($this->cacheKey());
            cache()->put($this->cacheKey(), [
                'version' => $this->version,
                'title'   => $this->title,
                'content' => $this->content,
                'parameters' => $this->parameters,
                'updated_at' => $this->updated_at,
                'checksum' => $this->checksum,
            ], now()->addHours(6));
        });
    }

    public function cacheKey(): string
    {
        return 'agent:'.$this->agent->slug.':prompt_active';
    }
}
