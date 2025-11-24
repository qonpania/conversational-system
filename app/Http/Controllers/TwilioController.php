<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioController extends Controller
{
    public function voice(Request $request)
    {
        // The URL of your WebSocket server (exposed via ngrok)
        // We'll use a config value or env var, but for now we can default to the ngrok URL logic
        // Ideally, this comes from an environment variable TWILIO_STREAM_URL
        // For this implementation, we'll assume the user will set APP_URL or a specific TWILIO_WEBSOCKET_URL

        $streamUrl = config('services.twilio.websocket_url');

        if (!$streamUrl) {
             // Fallback logic or error if not configured
             // For now, let's assume the user will replace this or we use a placeholder
             // We can also try to infer it from the request host if it's ngrok
             $host = $request->getHost();
             $streamUrl = "wss://{$host}/media-stream";
        }

        $response = new Response(
            '<?xml version="1.0" encoding="UTF-8"?>
            <Response>
                <Connect>
                    <Stream url="' . $streamUrl . '" />
                </Connect>
                <Pause length="1"/>
                <Say language="es-ES">La llamada ha finalizado.</Say>
            </Response>',
            200,
            ['Content-Type' => 'application/xml']
        );

        return $response;
    }
}
