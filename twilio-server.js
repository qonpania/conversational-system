import express from 'express';
import { WebSocketServer, WebSocket } from 'ws';
import dotenv from 'dotenv';
import pkg from 'wavefile';
const { WaveFile } = pkg;
import http from 'http';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 8082;
const API_KEY = process.env.GOOGLE_API_KEY;

if (!API_KEY) {
    console.error('❌ GOOGLE_API_KEY is missing in .env');
    process.exit(1);
}

// --- Global Audio Helpers (Pre-computed) ---

// 1. Mu-law LUT Generation
const pcmToMuLawMap = new Uint8Array(65536);
const muLawToPcmMap = new Int16Array(256);

function generateLuts() {
    console.log("⚙️  Generating Audio LUTs...");

    // 1. Mu-law -> PCM (Using WaveFile)
    const muLawBytes = new Uint8Array(256);
    for(let i=0; i<256; i++) muLawBytes[i] = i;

    const wavIn = new WaveFile();
    wavIn.fromScratch(1, 8000, '8m', muLawBytes);
    wavIn.fromMuLaw();
    const pcmSamples = new Int16Array(wavIn.data.samples.buffer, wavIn.data.samples.byteOffset, wavIn.data.samples.length / 2);

    for(let i=0; i<256; i++) {
        muLawToPcmMap[i] = pcmSamples[i];
    }

    // 2. PCM -> Mu-law (Using WaveFile to populate LUT)
    // We generate the mapping for all 65536 possible Int16 values.
    const pcmValues = new Int16Array(65536);
    for (let i = 0; i < 65536; i++) {
        pcmValues[i] = i - 32768;
    }

    const wavOut = new WaveFile();
    wavOut.fromScratch(1, 8000, '16', pcmValues);
    wavOut.toMuLaw();
    const muLawOut = wavOut.data.samples;

    for (let i = 0; i < 65536; i++) {
        pcmToMuLawMap[i] = muLawOut[i];
    }

    console.log("✅ Audio LUTs generated.");
}

generateLuts();

function muLawToPcm(muLawBuffer) {
    const pcmBuffer = new Int16Array(muLawBuffer.length);
    for (let i = 0; i < muLawBuffer.length; i++) {
        pcmBuffer[i] = muLawToPcmMap[muLawBuffer[i]];
    }
    return pcmBuffer;
}

function pcmToMuLaw(pcmBuffer) {
    const muLawBuffer = new Uint8Array(pcmBuffer.length);
    for (let i = 0; i < pcmBuffer.length; i++) {
        // Uint16 view of Int16: value + 32768 maps -32768 to 0
        let index = pcmBuffer[i] + 32768;
        muLawBuffer[i] = pcmToMuLawMap[index];
    }
    return Buffer.from(muLawBuffer);
}

// Stateful Linear Interpolation Upsample 8k -> 24k
function upsample8kTo24k(pcm8k, state) {
    const pcm24k = new Int16Array(pcm8k.length * 3);
    let prev = state.lastSample;

    for (let i = 0; i < pcm8k.length; i++) {
        const curr = pcm8k[i];

        // Interpolate
        pcm24k[i * 3] = prev + (curr - prev) * 0.333;
        pcm24k[i * 3 + 1] = prev + (curr - prev) * 0.666;
        pcm24k[i * 3 + 2] = curr;

        prev = curr;
    }

    state.lastSample = prev;
    return Buffer.from(pcm24k.buffer);
}

// Downsample 24k -> 8k (Averaging)
function downsample24kTo8k(pcm24k) {
    const pcm8k = new Int16Array(Math.floor(pcm24k.length / 3));
    for (let i = 0; i < pcm8k.length; i++) {
        const sum = pcm24k[i * 3] + pcm24k[i * 3 + 1] + pcm24k[i * 3 + 2];
        pcm8k[i] = Math.round(sum / 3);
    }
    return pcm8k;
}

// --- Server Setup ---

app.use(express.urlencoded({ extended: true }));

app.post('/twilio/voice', (req, res) => {
    const host = req.headers.host;
    const twiml = `<?xml version="1.0" encoding="UTF-8"?>
    <Response>
        <Connect>
            <Stream url="wss://${host}/media-stream" />
        </Connect>
        <Pause length="1"/>
        <Say language="es-ES">La llamada ha finalizado.</Say>
    </Response>`;

    res.type('text/xml');
    res.send(twiml);
});

const server = http.createServer(app);
const wss = new WebSocketServer({ server });

console.log(`📞 Twilio Server (HTTP + WS) running on port ${PORT}`);

wss.on('connection', (ws, req) => {
    console.log('New Twilio Connection');

    let streamSid = null;
    let geminiWs = null;
// Calculate RMS (Root Mean Square) to detect speech energy
function calculateRMS(pcmBuffer) {
    let sum = 0;
    for (let i = 0; i < pcmBuffer.length; i++) {
        sum += pcmBuffer[i] * pcmBuffer[i];
    }
    return Math.sqrt(sum / pcmBuffer.length);
}

    ws.streamState = { lastSample: 0 };
    ws.isGeminiSpeaking = false; // Track if Gemini is currently talking
    let lastAudioTime = Date.now();
    let silenceInterval = null;

    // Connect to Gemini
    const targetUrl = `wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key=${API_KEY}`;
    geminiWs = new WebSocket(targetUrl);

    // Silence Timeout Logic
    silenceInterval = setInterval(() => {
        if (Date.now() - lastAudioTime > 15000) { // 15 seconds
            console.log("⏱️ Silence timeout (15s). Closing call.");
            if (ws.readyState === WebSocket.OPEN) ws.close();
            if (geminiWs && geminiWs.readyState === WebSocket.OPEN) geminiWs.close();
        }
    }, 1000);

    geminiWs.on('open', () => {
        console.log('✅ Connected to Gemini API');

        const setupMessage = {
            setup: {
                model: "models/gemini-2.5-flash-native-audio-preview-09-2025",
                generationConfig: {
                    responseModalities: ["AUDIO"],
                    speechConfig: {
                        voiceConfig: { prebuiltVoiceConfig: { voiceName: "Aoede" } }
                    }
                },
                systemInstruction: {
                    parts: [{ text: "Eres un asistente telefónico útil y amable. Tu objetivo es ayudar al usuario. Responde de forma concisa y natural." }]
                }
            }
        };
        geminiWs.send(JSON.stringify(setupMessage));

        // Trigger Initial Greeting
        setTimeout(() => {
            if (geminiWs.readyState === WebSocket.OPEN) {
                const initialMsg = {
                    clientContent: {
                        turns: [{
                            role: "user",
                            parts: [{ text: "La llamada ha comenzado. Salúdame amablemente y pregúntame en qué puedes ayudarme hoy." }]
                        }],
                        turnComplete: true
                    }
                };
                geminiWs.send(JSON.stringify(initialMsg));
                console.log("👋 Triggered initial greeting");
            }
        }, 500);
    });

    geminiWs.on('error', (err) => {
        console.error('❌ Gemini WebSocket Error:', err);
    });

    geminiWs.on('close', (code, reason) => {
        console.log(`Gemini connection closed: ${code} ${reason}`);
        if (ws.readyState === WebSocket.OPEN) ws.close();
        clearInterval(silenceInterval);
    });

    geminiWs.on('message', (data) => {
        try {
            const response = JSON.parse(data);

            // Log everything that isn't audio to debug
            if (!response.serverContent?.modelTurn?.parts?.some(p => p.inlineData)) {
                // console.log("📩 Gemini Message:", JSON.stringify(response).substring(0, 200));
            }

            if (response.serverContent?.turnComplete) {
                ws.isGeminiSpeaking = false; // Gemini finished its turn
            }

            if (response.serverContent && response.serverContent.modelTurn && response.serverContent.modelTurn.parts) {
                for (const part of response.serverContent.modelTurn.parts) {
                    if (part.inlineData && part.inlineData.mimeType.startsWith('audio/pcm')) {
                        ws.isGeminiSpeaking = true; // Gemini is sending audio

                        const pcmData = Buffer.from(part.inlineData.data, 'base64');

                        // PCM 24k -> PCM 8k
                        const pcm24k = new Int16Array(pcmData.buffer, pcmData.byteOffset, pcmData.length / 2);
                        const pcm8k = downsample24kTo8k(pcm24k);

                        // PCM 8k -> Mu-law (LUT - Zero Latency)
                        const muLawData = pcmToMuLaw(pcm8k);

                        if (Math.random() < 0.01) console.log(`🔊 Received audio from Gemini (${pcmData.length} bytes)`);

                        // Send to Twilio
                        if (streamSid && ws.readyState === WebSocket.OPEN) {
                            const payload = {
                                event: 'media',
                                streamSid: streamSid,
                                media: {
                                    payload: muLawData.toString('base64')
                                }
                            };
                            ws.send(JSON.stringify(payload));
                        }
                    }
                }
            }
        } catch (e) {
            console.error('Error parsing Gemini message:', e);
        }
    });

    ws.on('message', (message) => {
        try {
            const msg = JSON.parse(message);

            switch (msg.event) {
                case 'start':
                    streamSid = msg.start.streamSid;
                    console.log(`Stream started: ${streamSid}`);
                    break;

                case 'media':
                    lastAudioTime = Date.now(); // Reset timeout

                    if (geminiWs && geminiWs.readyState === WebSocket.OPEN) {
                        const payload = Buffer.from(msg.media.payload, 'base64');

                        // Log occasional packets to confirm flow
                        if (Math.random() < 0.01) console.log(`🎤 Received audio from Twilio (${payload.length} bytes)`);

                        // Twilio (Mu-law 8k) -> PCM 16-bit 8k
                        const pcm8k = muLawToPcm(payload);

                        // BARGE-IN LOGIC: "Debounced" VAD (Voice Activity Detection)
                        // To prevent interruption from short noises (claps, taps), we require
                        // sustained energy for multiple frames.

                        const rms = calculateRMS(pcm8k);
                        const SPEECH_THRESHOLD = 1500; // Energy threshold
                        const FRAMES_REQUIRED = 3;     // ~60ms of speech required

                        if (rms > SPEECH_THRESHOLD) {
                            ws.speechFrameCount = (ws.speechFrameCount || 0) + 1;
                        } else {
                            ws.speechFrameCount = 0;
                        }

                        if (ws.isGeminiSpeaking && ws.speechFrameCount >= FRAMES_REQUIRED) {
                            console.log(`🛑 Barge-in detected (RMS: ${Math.round(rms)} | Frames: ${ws.speechFrameCount}). Clearing Twilio buffer.`);

                            // 1. Send Clear message to Twilio to stop playback immediately
                            const clearMsg = {
                                event: 'clear',
                                streamSid: streamSid
                            };
                            ws.send(JSON.stringify(clearMsg));

                            // 2. Mark Gemini as not speaking locally
                            ws.isGeminiSpeaking = false;
                            ws.speechFrameCount = 0; // Reset counter
                        }

                        // Apply 4x Digital Gain
                        for (let i = 0; i < pcm8k.length; i++) {
                            let s = pcm8k[i] * 4;
                            if (s > 32767) s = 32767;
                            if (s < -32768) s = -32768;
                            pcm8k[i] = s;
                        }

                        // PCM 8k -> PCM 24k (Stateful)
                        const pcm24k = upsample8kTo24k(pcm8k, ws.streamState);

                        const clientContent = {
                            realtimeInput: {
                                mediaChunks: [{
                                    mimeType: "audio/pcm",
                                    data: pcm24k.toString('base64')
                                }]
                            }
                        };
                        geminiWs.send(JSON.stringify(clientContent));
                    }
                    break;

                case 'stop':
                    console.log(`Stream stopped: ${streamSid}`);
                    if (geminiWs) geminiWs.close();
                    clearInterval(silenceInterval);
                    break;
            }
        } catch (e) {
            console.error('Error parsing Twilio message:', e);
        }
    });

    ws.on('close', () => {
        console.log('Twilio connection closed');
        if (geminiWs) geminiWs.close();
        clearInterval(silenceInterval);
    });

    ws.on('error', (err) => {
        console.error('❌ Twilio WebSocket Error:', err);
    });
});

server.listen(PORT);
