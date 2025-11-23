import { GeminiLiveClient } from './gemini-live-client.js';

const client = new GeminiLiveClient();
const toggleBtn = document.getElementById('toggleBtn');
const status = document.getElementById('status');
const sphere = document.getElementById('sphere');
let isConnected = false;

// Visualizer Logic (60fps loop)
function updateVisualizer() {
    if (isConnected && client) {
        const volume = client.getAudioVolume();
        // Set CSS variable for advanced CSS-based animations
        sphere.style.setProperty('--audio-level', volume);
    }
    requestAnimationFrame(updateVisualizer);
}
// Start the loop
requestAnimationFrame(updateVisualizer);

const defaultClasses = ['bg-white', 'text-[#050505]', 'shadow-[0_0_20px_rgba(255,255,255,0.1)]', 'hover:shadow-[0_0_30px_rgba(255,255,255,0.2)]'];
const stopClasses = ['bg-red-500/20', 'text-red-300', 'border', 'border-red-500/50', 'shadow-none', 'hover:bg-red-500/30', 'hover:shadow-[0_0_20px_rgba(239,68,68,0.2)]'];

function setButtonState(isStop) {
    if (isStop) {
        toggleBtn.classList.remove(...defaultClasses);
        toggleBtn.classList.add(...stopClasses);
    } else {
        toggleBtn.classList.remove(...stopClasses);
        toggleBtn.classList.add(...defaultClasses);
    }
}

// Handle server-side disconnects
client.onDisconnect(() => {
    if (isConnected) {
        isConnected = false;
        status.textContent = "Disconnected by Server";
        toggleBtn.textContent = "Start Conversation";
        setButtonState(false);
        toggleBtn.disabled = false;
        sphere.style.transform = 'scale(1)';
        sphere.style.setProperty('--audio-level', 0);
    }
});

if (toggleBtn) {
    toggleBtn.addEventListener('click', async () => {
        if (!isConnected) {
            status.textContent = "Connecting...";
            toggleBtn.disabled = true;
            try {
                await client.connect();
                isConnected = true;
                status.textContent = "Listening";
                toggleBtn.textContent = "End Conversation";
                setButtonState(true);
            } catch (e) {
                status.textContent = "Error: " + e.message;
                console.error(e);
            } finally {
                toggleBtn.disabled = false;
            }
        } else {
            client.stop();
            isConnected = false;
            status.textContent = "Ready";
            toggleBtn.textContent = "Start Conversation";
            setButtonState(false);
            sphere.style.transform = 'scale(1)';
        }
    });
}
