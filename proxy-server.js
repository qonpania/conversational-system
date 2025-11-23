import { WebSocketServer, WebSocket } from 'ws';
import dotenv from 'dotenv';

// Load environment variables
dotenv.config();

const API_KEY = process.env.GOOGLE_API_KEY;
const PORT = 8081;

if (!API_KEY) {
    console.error("❌ Error: GOOGLE_API_KEY not found in .env");
    process.exit(1);
}

const wss = new WebSocketServer({ port: PORT });

console.log(`🚀 Secure Proxy Server running on ws://localhost:${PORT}`);

wss.on('connection', (clientWs) => {
    console.log('Client connected to proxy');

    const targetUrl = `wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key=${API_KEY}`;
    const targetWs = new WebSocket(targetUrl);

    const messageBuffer = [];

    targetWs.on('open', () => {
        console.log('✅ Connected to Gemini API');
        // Flush buffer
        while (messageBuffer.length > 0) {
            const msg = messageBuffer.shift();
            targetWs.send(msg);
        }
    });

    targetWs.on('message', (data) => {
        if (clientWs.readyState === WebSocket.OPEN) {
            clientWs.send(data);
        }
    });

    targetWs.on('error', (error) => {
        console.error('❌ Gemini API Error:', error.message);
        clientWs.close();
    });

    targetWs.on('close', () => {
        console.log('Gemini API connection closed');
        clientWs.close();
    });

    clientWs.on('message', (data) => {
        if (targetWs.readyState === WebSocket.OPEN) {
            targetWs.send(data);
        } else {
            console.log('⏳ Buffering message for upstream...');
            messageBuffer.push(data);
        }
    });

    clientWs.on('close', () => {
        console.log('Client disconnected');
        targetWs.close();
    });

    clientWs.on('error', (error) => {
        console.error('❌ Client Error:', error.message);
        targetWs.close();
    });
});
