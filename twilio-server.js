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
    const SANTIAGO_PROMPT = `
    Rol y Objetivo
    Eres SantIAgo, el asesor telefónico experto de Peru Fibra. Tu voz es cálida, profesional, empática y clara. Tu objetivo es validar la identidad del cliente, verificar si su casa tiene cobertura técnica y, de ser positivo, ofrecerle los mejores planes de internet.

    IMPORTANTE (Contexto de Voz):

    Tus respuestas deben ser cortas y conversacionales. Evita listas largas o textos de relleno.

    No uses formato visual (Markdown, asteriscos, negritas) porque el sintetizador de voz (TTS) podría leerlos mal.

    Si necesitas pensar o consultar una herramienta, usa una frase de relleno ("Permíteme verificar eso un segundo...").

    Herramientas / Webhooks Disponibles
    Debes llamar a estas herramientas en el momento preciso según el flujo:

    validar_identidad(tipo_doc, numero_doc): Valida DNI o CE. Retorna el nombre del cliente.

    validar_cobertura(direccion_completa): Envía la dirección para geocodificación y validación técnica. Retorna coordenadas y un estado (TIENE_COBERTURA o SIN_COBERTURA).

    consultar_rag(pregunta): ÚSALA SIEMPRE que el usuario pregunte sobre planes, precios, velocidades, características del servicio, cobertura general o información de la empresa. NO inventes respuestas.

    Flujo Conversacional Estricto
    Paso 1: Saludo y Nombre
    Saluda amablemente identificándote como SantIAgo de Peru Fibra.

    Pregunta el nombre del usuario.

    Ejemplo: "¡Hola! Soy SantIAgo de Peru Fibra. ¿Con quién tengo el gusto de hablar?"

    Paso 2: Solicitud de Documento
    Una vez te den el nombre, pide su documento de identidad (DNI o Carnet de Extranjería) para validar sus datos.

    Acción: En cuanto te den el número, ejecuta validar_identidad.

    Si la herramienta confirma el nombre, úsalo para dirigirte a él/ella.

    Paso 3: Dirección de Instalación (Crítico)
    Pide la dirección donde desean instalar el servicio.

    Instrucción Clave: Para que la validación funcione, debes pedir explícitamente: Departamento, Provincia, Distrito, Avenida/Calle y Número/Lote.

    Ejemplo: "Gracias [Nombre]. Para verificar si llegamos a tu hogar, necesito tu dirección exacta. Por favor indícame el Departamento, Distrito, calle y número."

    Si el usuario da una dirección vaga (ej: "Vivo en la Av. Arequipa"), repregunta amable por el número y el distrito.

    Paso 4: Validación de Cobertura
    Una vez tengas la dirección completa, di algo como: "Entendido, voy a validar la cobertura en esa ubicación, dame un momento..."

    Acción: Ejecuta validar_cobertura.

    Escenario A: ¡HAY COBERTURA! (Happy Path)

    Tono: Alegre y entusiasta.

    Ejemplo: "¡Excelentes noticias! Confirmado, sí tenemos cobertura de fibra óptica directa en tu dirección."

    Pasa inmediatamente al Paso 5.

    Escenario B: NO HAY COBERTURA (Sad Path)

    Tono: Empático y amable.

    Ejemplo: "Lo siento mucho. Acabo de revisar el sistema y por el momento nuestra red no llega a esa dirección específica. Pero guardaremos tus datos para avisarte apenas ampliemos la zona."

    Despídete amablemente y termina la llamada.

    Paso 5: Oferta de Servicios
    Solo si hubo cobertura.

    Acción: Ejecuta consultar_rag con la pregunta "¿Qué planes tienen?" para obtener la oferta actual.

    Presenta la oferta de forma atractiva pero resumida. No leas una tabla gigante. Pregunta qué necesita.

    Ejemplo: "Tenemos planes de 100, 200 y hasta 1000 megas. ¿Qué uso le sueles dar al internet? ¿Es para trabajo, juegos o ver series?"

    Regla de "Interrupción Inteligente"
    El usuario puede preguntar sobre los planes en cualquier momento (incluso antes de dar su DNI).

    Si el usuario pregunta: "¿Qué precios tienen?", responde brevemente usando consultar_rag.

    Inmediatamente después, retoma el control del flujo amablemente: "...los precios parten desde X soles. Pero para confirmarte si podemos instalarlo, ¿podrías ayudarme con tu número de DNI primero?"

    Directrices de Comportamiento
    Validación de Dirección: Si la herramienta validar_cobertura retorna error por dirección ambigua, pide al usuario referencias adicionales (cruce de calles, color de casa) y vuelve a intentar.

    Manejo de Errores: Si una herramienta falla (error técnico), no digas "Error 500". Di: "Tengo un pequeño retraso en el sistema, ¿podrías repetirme ese dato?"

    Cierre de Venta: Tu meta final es agendar la instalación o derivar a un humano para el contrato final.
    `;

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
        const fromNumber = req.body.From || 'Unknown';
        const toNumber = req.body.To || 'Unknown';

        // Construct the callback URL dynamically based on the host (ngrok or production)
        // Note: In production, ensure 'host' is your public domain.
        const callbackUrl = `https://${host}/api/twilio/call-status`;

        const twiml = `<?xml version="1.0" encoding="UTF-8"?>
        <Response>
            <Start>
                <Recording recordingStatusCallback="${callbackUrl}" recordingStatusCallbackEvent="completed" recordingChannels="dual" />
            </Start>
            <Connect>
                <Stream url="wss://${host}/media-stream">
                    <Parameter name="from" value="${fromNumber}" />
                    <Parameter name="to" value="${toNumber}" />
                </Stream>
            </Connect>
            <Pause length="1"/>
            <Say language="es-ES">La llamada ha finalizado.</Say>
        </Response>`;

        res.type('text/xml');
        res.send(twiml);
    });

    // Proxy for Twilio Call Status / Recording Callback
    app.post('/api/twilio/call-status', async (req, res) => {
        console.log("📨 Received Twilio Callback via Proxy:", JSON.stringify(req.body));

        const apiUrl = process.env.APP_URL ? `${process.env.APP_URL}/api/twilio/call-status` : 'http://localhost:8000/api/twilio/call-status';

        try {
            const apiRes = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(req.body)
            });

            if (apiRes.ok) {
                console.log("✅ Forwarded callback to Laravel successfully.");
                res.status(200).send('OK');
            } else {
                console.error(`❌ Laravel API returned ${apiRes.status}`);
                res.status(apiRes.status).send('Error forwarding');
            }
        } catch (err) {
            console.error("❌ Error forwarding callback to Laravel:", err.message);
            res.status(500).send('Internal Server Error');
        }
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
            // Increased to 60 seconds to allow for slow tool execution
            if (Date.now() - lastAudioTime > 60000) {
                console.log("⏱️ Silence timeout (60s). Closing call.");
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
                        parts: [{ text: SANTIAGO_PROMPT }]
                    },
                    tools: [{
                        functionDeclarations: [
                            {
                                name: "validar_identidad",
                                description: "Valida DNI o CE y retorna el nombre del cliente.",
                                parameters: {
                                    type: "OBJECT",
                                    properties: {
                                        tipo_doc: { type: "STRING", description: "Tipo de documento: 'DNI' o 'CE'." },
                                        numero_doc: { type: "STRING", description: "Número de documento." }
                                    },
                                    required: ["tipo_doc", "numero_doc"]
                                }
                            },
                            {
                                name: "validar_cobertura",
                                description: "Valida si hay cobertura técnica en la dirección indicada.",
                                parameters: {
                                    type: "OBJECT",
                                    properties: {
                                        direccion_completa: { type: "STRING", description: "Dirección completa (Departamento, Distrito, Calle, Número)." }
                                    },
                                    required: ["direccion_completa"]
                                }
                            },
                            {
                                name: "consultar_rag",
                                description: "Consulta la base de conocimientos para responder preguntas sobre planes, servicios, precios y la empresa.",
                                parameters: {
                                    type: "OBJECT",
                                    properties: {
                                        pregunta: { type: "STRING", description: "La pregunta completa del usuario." }
                                    },
                                    required: ["pregunta"]
                                }
                            }
                        ]
                    }]
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
                                parts: [{ text: "La llamada ha comenzado. Actúa como SantIAgo según el prompt del sistema: saluda con la apertura indicada, preséntate como asesor digital de Peru Fibra y pide el nombre completo del cliente antes de continuar." }]
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

        geminiWs.on('message', async (data) => {
            try {
                const response = JSON.parse(data);

                // Log tool calls specifically to debug
                if (response.toolCall) {
                    console.log("🛠️  Gemini Tool Call Received:", JSON.stringify(response.toolCall));
                }

                if (response.serverContent?.turnComplete) {
                    ws.isGeminiSpeaking = false;
                }

                // Handle Audio
                if (response.serverContent?.modelTurn?.parts) {
                    for (const part of response.serverContent.modelTurn.parts) {
                        if (part.inlineData && part.inlineData.mimeType.startsWith('audio/pcm')) {
                            ws.isGeminiSpeaking = true;
                            const pcmData = Buffer.from(part.inlineData.data, 'base64');
                            const pcm24k = new Int16Array(pcmData.buffer, pcmData.byteOffset, pcmData.length / 2);
                            const pcm8k = downsample24kTo8k(pcm24k);
                            const muLawData = pcmToMuLaw(pcm8k);

                            if (streamSid && ws.readyState === WebSocket.OPEN) {
                                const payload = {
                                    event: 'media',
                                    streamSid: streamSid,
                                    media: { payload: muLawData.toString('base64') }
                                };
                                ws.send(JSON.stringify(payload));
                            }
                        }
                    }
                }

                // Handle Tool Calls
                if (response.toolCall) {
                    const functionCalls = response.toolCall.functionCalls;
                    const toolResponses = [];

                    for (const call of functionCalls) {
                        const { name, args, id } = call; // Ensure we capture the ID
                        let result = {};

                        console.log(`🚀 Executing ${name} (ID: ${id}) with args:`, JSON.stringify(args));

                        try {
                            let webhookUrl = "";

                            if (name === 'validar_identidad') {
                            // The n8n workflow 'call-get-personal-data' handles DNI, CE, and RUC routing internally.
                            webhookUrl = "https://n8n.joyeria-sharvel.com/webhook/call-get-personal-data";

                            // Ensure args are passed correctly as expected by n8n
                            // n8n expects: { tipo_doc: "...", numero_doc: "..." }
                            // Gemini should already provide these, but we ensure they are present.
                        } else if (name === 'validar_cobertura') {
                                webhookUrl = "https://n8n.joyeria-sharvel.com/webhook/sharvel-get-address";
                                // Map args: webhook expects { address: ... }
                                if (!args.address && args.direccion_completa) {
                                    args.address = args.direccion_completa;
                                }

                            } else if (name === 'consultar_rag') {
                                webhookUrl = "https://n8n.joyeria-sharvel.com/webhook/call-query-rag";
                            }

                            if (webhookUrl) {
                                const apiRes = await fetch(webhookUrl, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(args)
                                });

                                if (!apiRes.ok) {
                                    throw new Error(`Webhook status ${apiRes.status}`);
                                }

                                const text = await apiRes.text();
                                try {
                                    result = JSON.parse(text);
                                } catch (jsonErr) {
                                    result = { output: text };
                                }
                            } else {
                                result = { error: `Tool ${name} not implemented` };
                            }

                        } catch (err) {
                            console.error(`❌ Tool execution failed:`, err);
                            result = { error: err.message };
                        }

                        // Ensure result is an object for the response
                        if (typeof result !== 'object' || result === null) {
                            result = { result: result };
                        }

                        toolResponses.push({
                            id: id, // Critical: Must match the call ID
                            name: name,
                            response: result // Pass the object directly, not wrapped in { result: ... }
                        });
                    }

                    const toolResponseMsg = {
                        toolResponse: {
                            functionResponses: toolResponses
                        }
                    };

                    console.log("📤 Sending Tool Response:", JSON.stringify(toolResponseMsg));
                    geminiWs.send(JSON.stringify(toolResponseMsg));
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
                        ws.callSid = msg.start.callSid;
                        ws.callStartTime = Date.now();

                        console.log("🚀 Start Event Received:", JSON.stringify(msg.start));

                        // Try to get 'from' from customParameters (injected via TwiML) or standard parameters
                        ws.fromNumber = msg.start.customParameters?.from || msg.start.from || msg.start.customParameters?.From;

                        console.log(`Stream started: ${streamSid}, CallSid: ${ws.callSid}, From: ${ws.fromNumber}`);
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

                        // Log call to Laravel API
                        if (ws.callSid) {
                            const duration = Math.round((Date.now() - ws.callStartTime) / 1000);
                            const apiUrl = process.env.APP_URL ? `${process.env.APP_URL}/api/twilio/call-status` : 'http://localhost:8000/api/twilio/call-status';

                            console.log(`📝 Logging call ${ws.callSid} to ${apiUrl} (Duration: ${duration}s)`);

                            fetch(apiUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    CallSid: ws.callSid,
                                    CallStatus: 'completed',
                                    CallDuration: duration,
                                    Direction: 'inbound', // Assuming inbound for now
                                    From: ws.fromNumber
                                })
                            }).catch(err => console.error('❌ Failed to log call to API:', err.message));
                        }
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
