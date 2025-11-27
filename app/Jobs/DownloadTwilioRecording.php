<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DownloadTwilioRecording implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    protected $call;
    protected $recordingUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Call $call, string $recordingUrl)
    {
        $this->call = $call;
        $this->recordingUrl = $recordingUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->recordingUrl) {
            return;
        }

        // Append .mp3 to ensure we get the audio file
        $url = $this->recordingUrl . '.mp3';

        try {
            // Use Basic Auth with Twilio Credentials
            $sid = env('TWILIO_ACCOUNT_SID');
            $token = env('TWILIO_AUTH_TOKEN');

            if (!$sid || !$token) {
                \Illuminate\Support\Facades\Log::error("Twilio credentials missing in .env (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN)");
                return;
            }

            $response = \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)->get($url);

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error("Failed to download recording from {$url}. Status: " . $response->status());
                return;
            }

            $content = $response->body();

            $filename = 'recordings/' . $this->call->call_sid . '.mp3';

            // Save to public disk
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $content);

            // Update call with local URL
            $this->call->recording_url = \Illuminate\Support\Facades\Storage::url($filename);
            $this->call->save();

            \Illuminate\Support\Facades\Log::info("Recording saved for call {$this->call->call_sid}");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error downloading recording: " . $e->getMessage());
        }
    }
}
