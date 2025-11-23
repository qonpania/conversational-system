/**
 * Configuration constants for the Gemini Live Client.
 */
const CONFIG = {
    SAMPLE_RATE: 24000,
    BUFFER_SIZE: 4096,
    CHANNELS: 1,
    API: {
        // Point to local proxy
        BASE_URL: 'ws://localhost:8081',
        MODEL: "models/gemini-2.5-flash-native-audio-preview-09-2025",
        VOICE: "Aoede"
    },
    SYSTEM_INSTRUCTION: "Eres un asistente de IA que puede responder preguntas en español."
};

/**
 * Client for interacting with the Gemini Multimodal Live API via WebSocket.
 * Handles audio recording, streaming, and playback.
 */
export class GeminiLiveClient {
    constructor() {
        this.ws = null;
        this.audioContext = null;
        this.mediaStream = null;
        this.processor = null;
        this.analyser = null;
        this.audioQueue = [];
        this.nextStartTime = 0;
        this.onAudioLevelCallback = null;
        this.onDisconnectCallback = null;
    }

    /**
     * Register a callback for audio level updates (for visualization).
     * @param {function(number): void} callback - Function receiving RMS value (0-1).
     */
    onAudioLevel(callback) {
        this.onAudioLevelCallback = callback;
    }

    /**
     * Register a callback for server disconnection events.
     * @param {function(): void} callback - Function called on disconnect.
     */
    onDisconnect(callback) {
        this.onDisconnectCallback = callback;
    }

    /**
     * Establishes connection to the Gemini API via Proxy.
     */
    async connect() {
        try {
            // Connect to Proxy (no key needed on frontend)
            const url = CONFIG.API.BASE_URL;
            this.ws = new WebSocket(url);

            this.ws.onopen = () => {
                console.log('✅ Connected to Proxy WebSocket');
                this._sendInitialSetup();
                this._startAudioInput();
            };

            this.ws.onmessage = (event) => this._handleMessage(event);
            this.ws.onerror = (error) => console.error('❌ WebSocket error:', error);
            this.ws.onclose = (event) => this._handleClose(event);

        } catch (error) {
            console.error('❌ Connection failed:', error);
            throw error;
        }
    }

    /**
     * Stops the client, closes connections, and releases resources.
     */
    stop() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        if (this.mediaStream) {
            this.mediaStream.getTracks().forEach(track => track.stop());
            this.mediaStream = null;
        }
        if (this.audioContext && this.audioContext.state !== 'closed') {
            this.audioContext.close();
        }
        this.audioContext = null;
        this.processor = null;
        this.analyser = null;
        this.audioQueue = [];
    }

    /**
     * Returns the current audio volume level (0-1).
     * @returns {number} RMS volume.
     */
    getAudioVolume() {
        if (!this.analyser) return 0;
        const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
        this.analyser.getByteFrequencyData(dataArray);

        let sum = 0;
        for (let i = 0; i < dataArray.length; i++) {
            sum += dataArray[i];
        }
        return sum / dataArray.length / 255;
    }

    // =========================================
    // Private / Internal Methods
    // =========================================

    _getApiKey() {
        return document.querySelector('meta[name="gemini-api-key"]')?.content;
    }

    async _fetchTokenFromBackend() {
        try {
            const response = await fetch('/api/gemini/token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });
            if (response.ok) {
                const data = await response.json();
                return data.token || data.accessToken;
            }
        } catch (e) {
            // Silent fail
        }
        return null;
    }

    _sendInitialSetup() {
        console.log('📤 Sending initial setup...');
        const setupMessage = {
            setup: {
                model: CONFIG.API.MODEL,
                generationConfig: {
                    responseModalities: ["AUDIO"],
                    speechConfig: {
                        voiceConfig: { prebuiltVoiceConfig: { voiceName: CONFIG.API.VOICE } }
                    }
                },
                systemInstruction: {
                    parts: [{ text: CONFIG.SYSTEM_INSTRUCTION }]
                }
            }
        };
        this.ws.send(JSON.stringify(setupMessage));
    }

    async _startAudioInput() {
        console.log('🎤 Starting audio input...');
        this.nextStartTime = 0; // Reset playback timing

        this.audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: CONFIG.SAMPLE_RATE });
        this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        console.log('🎤 Microphone access granted');

        const source = this.audioContext.createMediaStreamSource(this.mediaStream);

        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 256;
        this.analyser.smoothingTimeConstant = 0.5;

        this.processor = this.audioContext.createScriptProcessor(CONFIG.BUFFER_SIZE, CONFIG.CHANNELS, CONFIG.CHANNELS);

        source.connect(this.analyser);
        this.analyser.connect(this.processor);
        this.processor.connect(this.audioContext.destination);

        let firstChunkLogged = false;
        this.processor.onaudioprocess = (e) => {
            if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;

            const inputData = e.inputBuffer.getChannelData(0);
            const pcmData = this._floatTo16BitPCM(inputData);
            const base64Audio = this._arrayBufferToBase64(pcmData);

            this.ws.send(JSON.stringify({
                realtimeInput: {
                    mediaChunks: [{
                        mimeType: `audio/pcm;rate=${CONFIG.SAMPLE_RATE}`,
                        data: base64Audio
                    }]
                }
            }));

            if (!firstChunkLogged) {
                console.log('📤 First audio chunk sent');
                firstChunkLogged = true;
            }
        };
    }

    async _handleMessage(event) {
        let data;
        if (event.data instanceof Blob) {
            data = JSON.parse(await event.data.text());
        } else {
            data = JSON.parse(event.data);
        }

        if (data.serverContent) {
            if (data.serverContent.interrupted) {
                console.log('🛑 Interruption detected! Stopping playback.');
                this._stopAudioPlayback();
                return;
            }

            if (data.serverContent.modelTurn && data.serverContent.modelTurn.parts) {
                console.log('🔊 Received audio response from Gemini');
                for (const part of data.serverContent.modelTurn.parts) {
                    if (part.inlineData && part.inlineData.mimeType.startsWith('audio/pcm')) {
                        this._playAudio(part.inlineData.data);
                    }
                }
            }
        }
    }

    _handleClose(event) {
        console.log(`🔌 Disconnected. Code: ${event.code}, Reason: ${event.reason}`);
        if (this.onDisconnectCallback) {
            this.onDisconnectCallback();
        }
        this.stop();
    }

    _stopAudioPlayback() {
        this.audioQueue.forEach(source => {
            try { source.stop(); } catch (e) {}
        });
        this.audioQueue = [];
        this.nextStartTime = this.audioContext.currentTime;
    }

    _playAudio(base64Data) {
        const binaryString = window.atob(base64Data);
        const len = binaryString.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        const pcm16 = bytes.buffer;
        const float32 = this._pcm16ToFloat32(pcm16);

        const buffer = this.audioContext.createBuffer(1, float32.length, CONFIG.SAMPLE_RATE);
        buffer.getChannelData(0).set(float32);

        const source = this.audioContext.createBufferSource();
        source.buffer = buffer;
        source.connect(this.audioContext.destination);

        const currentTime = this.audioContext.currentTime;
        if (this.nextStartTime < currentTime) {
            this.nextStartTime = currentTime;
        }
        source.start(this.nextStartTime);
        this.nextStartTime += buffer.duration;

        this.audioQueue.push(source);
        source.onended = () => {
            const index = this.audioQueue.indexOf(source);
            if (index > -1) {
                this.audioQueue.splice(index, 1);
            }
        };
    }

    // Utils
    _floatTo16BitPCM(input) {
        const output = new Int16Array(input.length);
        for (let i = 0; i < input.length; i++) {
            const s = Math.max(-1, Math.min(1, input[i]));
            output[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }
        return output.buffer;
    }

    _arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const len = bytes.byteLength;
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    _pcm16ToFloat32(buffer) {
        const int16 = new Int16Array(buffer);
        const float32 = new Float32Array(int16.length);
        for (let i = 0; i < int16.length; i++) {
            float32[i] = int16[i] / 32768.0;
        }
        return float32;
    }
}
