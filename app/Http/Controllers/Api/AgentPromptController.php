<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentPromptController extends Controller
{
    public function showActive(Request $request, string $slug)
    {
        $cacheKey = 'agent:'.$slug.':prompt_active';

        $data = cache()->remember($cacheKey, now()->addHours(6), function () use ($slug) {
            $agent = Agent::query()
                ->where('slug',$slug)
                ->with(['activePrompt'])
                ->firstOrFail();

            $p = $agent->activePrompt;
            abort_if(!$p, 404, 'No active prompt');

            return [
                'agent'      => ['slug'=>$agent->slug,'name'=>$agent->name],
                'version'    => $p->version,
                'title'      => $p->title,
                'content'    => $p->content,
                'parameters' => $p->parameters ?? new \stdClass(),
                'updated_at' => $p->updated_at?->toIso8601String(),
                'checksum'   => $p->checksum,
            ];
        });

        $etag = '"'.sha1(($data['checksum'] ?? '').'|'.$data['version']).'"';
        if ($request->getETags() && in_array($etag, $request->getETags())) {
            return response()->noContent(304)->setEtag($etag);
        }

        return response()->json($data)->setEtag($etag);
    }
}
