<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Gemini Live Voice</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Google Fonts: Outfit -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@200;300;400;500&display=swap');
    </style>
</head>
<body class="bg-[#050505] text-white font-sans flex flex-col justify-center items-center h-screen m-0 overflow-hidden">

    <!-- SVG Filter for extra gooeyness -->
    <svg class="absolute w-0 h-0">
        <filter id="goo">
            <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
            <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
            <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
        </filter>
    </svg>

    <!-- Visualizer Container -->
    <div class="relative w-[300px] h-[300px] flex justify-center items-center" id="sphere">

        <!-- Glow Effect -->
        <div class="glow absolute w-[120%] h-[120%] rounded-full -z-10 transition-all duration-100 ease-out bg-[radial-gradient(circle,rgba(139,92,246,0.2)_0%,rgba(0,0,0,0)_70%)]"
             style="opacity: calc(0.3 + var(--audio-level, 0) * 0.5); transform: scale(calc(1 + var(--audio-level, 0) * 0.2));">
        </div>

        <!-- Blob 1: Cyan/Blue -->
        <div class="blob absolute w-full h-full opacity-80 mix-blend-screen transition-transform duration-100 ease-out will-change-transform blur-[2px] animate-blob bg-gradient-to-br from-[#22d3ee] to-[#3b82f6]"
             style="transform: scale(calc(1 + var(--audio-level, 0) * 0.4));">
        </div>

        <!-- Blob 2: Violet/Pink -->
        <div class="blob absolute w-full h-full opacity-60 mix-blend-screen transition-transform duration-100 ease-out will-change-transform blur-[2px] animate-blob-reverse bg-gradient-to-br from-[#8b5cf6] to-[#d946ef]"
             style="transform: rotate(60deg) scale(calc(1 + var(--audio-level, 0) * 0.8));">
        </div>

        <!-- Blob 3: Peach/Orange -->
        <div class="blob absolute w-full h-full opacity-50 mix-blend-screen transition-transform duration-100 ease-out will-change-transform blur-[2px] animate-blob-slow bg-gradient-to-br from-[#f472b6] to-[#fb923c]"
             style="transform: rotate(120deg) scale(calc(1 + var(--audio-level, 0) * 0.6));">
        </div>
    </div>

    <!-- Controls -->
    <div class="mt-16 px-12 py-6 bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl flex flex-col items-center gap-4 transition-all duration-300">
        <div class="text-sm tracking-widest uppercase text-white/60 font-light" id="status">Ready</div>
        <button id="toggleBtn"
                class="bg-white text-[#050505] border-none px-12 py-4 rounded-full text-lg font-medium cursor-pointer transition-all duration-200 shadow-[0_0_20px_rgba(255,255,255,0.1)] hover:scale-105 hover:shadow-[0_0_30px_rgba(255,255,255,0.2)] active:scale-98 disabled:opacity-50 disabled:cursor-not-allowed">
            Start Conversation
        </button>
    </div>

    @vite(['resources/js/voice-call.js'])

</body>
</html>
