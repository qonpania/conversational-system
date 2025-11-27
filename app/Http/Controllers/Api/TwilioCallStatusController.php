<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Call;
use Illuminate\Support\Facades\Log;

class TwilioCallStatusController extends Controller
{
    public function store(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('Twilio Call Status Webhook', $request->all());

        $callSid = $request->input('CallSid');

        if (!$callSid) {
            return response()->json(['error' => 'Missing CallSid'], 400);
        }

        $call = Call::firstOrNew(['call_sid' => $callSid]);

        // Map Twilio parameters to our model
        // Only update if present to avoid overwriting with null (e.g. from recording callback)
        if ($request->has('Direction')) {
            $call->direction = $request->input('Direction');

            if ($request->input('Direction') === 'inbound') {
                $call->phone_number = $request->input('From');
            } else {
                $call->phone_number = $request->input('To');
            }
        }

        if ($request->has('CallStatus')) {
            $call->status = $request->input('CallStatus');
        }

        if ($request->has('CallDuration')) {
            $call->duration = $request->input('CallDuration');
        }

        // Calculate timestamps if completed
        if ($request->input('CallStatus') === 'completed' || $request->input('CallStatus') === 'no-answer') {
            $call->ended_at = now();
            if ($call->duration) {
                $call->started_at = now()->subSeconds($call->duration);
            } elseif (!$call->started_at) {
                 // If no duration (e.g. busy), assume it started recently or just now
                 $call->started_at = now();
            }
        }

        // Save first to ensure we have the ID and basic data
        $call->save();

        // Handle Recording Download
        if ($request->has('RecordingUrl')) {
            $recordingUrl = $request->input('RecordingUrl');

            // Dispatch job to download it.
            // Since we are using sync queue, this will happen immediately and update the record again.
            \App\Jobs\DownloadTwilioRecording::dispatch($call, $recordingUrl);
        }

        return response()->json(['status' => 'success']);
    }
}
