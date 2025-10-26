{{-- resources/views/filament/conversations/view.blade.php --}}
{{-- @php use Illuminate\Support\Str; @endphppp --}}

<x-filament::page>
    {{-- Resumen --}}
    <x-filament::section>
        <x-slot name="heading">Resumen</x-slot>

        <div class="prose max-w-none dark:prose-invert">
            @if ($this->record->summary)
                <p class="text-sm leading-relaxed whitespace-pre-line text-gray-800 dark:text-gray-100">
                    {{ $this->record->summary }}
                </p>

                <div class="text-xs mt-2 text-gray-500 dark:text-gray-400">
                    Actualizado:
                    {{ $this->record->summary_updated_at?->tz(config('app.timezone'))?->format('Y-m-d H:i') }}
                    @if ($meta = $this->record->summary_meta)
                        · Modelo: {{ $meta['model'] ?? '—' }} · Tokens: {{ $meta['tokens'] ?? '—' }}
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Aún no hay resumen.</p>
            @endif
        </div>

        <div class="mt-3">
            <x-filament::button wire:click="regenerateSummary" icon="heroicon-o-arrow-path">
                Regenerar resumen
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Chat --}}
    <div class="space-y-4 mt-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->record->channel->name }} • {{ strtoupper($this->record->status) }}
                </div>
                <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $this->record->contact->username ? '@' . $this->record->contact->username : $this->record->contact->name ?? 'Contacto' }}
                </div>
            </div>
            <div class="flex gap-2">
                <x-filament::badge>
                    Último:
                    {{ optional($this->record->last_message_at)->tz(config('app.timezone'))->format('Y-m-d H:i') }}
                </x-filament::badge>
            </div>
        </div>

        <!-- Chat container -->
        <div
            class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 h-[70vh] overflow-y-auto">
            @foreach ($this->record->messages as $m)
                @php $isOutbound = $m->direction === 'outbound'; @endphp

                <div class="mb-3 flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
                    <div
                        class="max-w-[75%] rounded-2xl px-4 py-2 shadow
                        {{ $isOutbound
                            ? 'bg-primary-600 text-white rounded-br-none'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-bl-none' }}">
                        @if ($m->type === 'text')
                            <div class="whitespace-pre-line text-sm leading-relaxed">
                                {{ $m->text }}
                            </div>
                        @else
                            <div class="text-xs opacity-80 mb-1">
                                ({{ strtoupper($m->type) }})
                            </div>

                            @if ($m->attachments)
                                @foreach ($m->attachments as $a)
                                    @if (Str::of($a['mime'] ?? '')->startsWith('image/'))
                                        <img src="{{ $a['url'] ?? '' }}" alt="{{ $a['filename'] ?? 'image' }}"
                                            class="rounded-lg mt-2 border border-gray-200 dark:border-gray-700">
                                    @else
                                        <a href="{{ $a['url'] ?? '#' }}" target="_blank"
                                            class="underline text-xs break-all text-blue-700 dark:text-blue-400">
                                            {{ $a['filename'] ?? 'archivo' }} ({{ $a['mime'] ?? 'file' }})
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        @endif

                        <div
                            class="mt-1 text-[10px] opacity-70 {{ $isOutbound ? 'text-white' : 'text-gray-600 dark:text-gray-400' }} text-right">
                            {{ optional($m->sent_at)->tz(config('app.timezone'))->format('Y-m-d H:i') }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($this->record->routing_mode === 'human')
        <x-filament::section class="mb-4">
            <x-slot name="heading">Responder como Administrador</x-slot>

            <form wire:submit.prevent="sendAdmin">
                <textarea wire:model.defer="adminText" rows="3" placeholder="Escribe tu mensaje..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>

                <div class="mt-2 flex items-center gap-2">
                    <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled">
                        Enviar al usuario
                    </x-filament::button>
                    <x-filament::badge color="warning">Modo: HUMANO</x-filament::badge>
                </div>
            </form>
        </x-filament::section>
    @endif
</x-filament::page>

@push('scripts')
    <script data-navigate-once>
        (function() {
            const convId = @json($this->record->id);

            function loadScript(src) {
                return new Promise((res, rej) => {
                    const s = document.createElement('script');
                    s.src = src;
                    s.async = true;
                    s.onload = res;
                    s.onerror = rej;
                    document.head.appendChild(s);
                });
            }

            async function ensureEcho() {
                if (!window.Echo || typeof window.Echo !== 'function') {
                    try {
                        await loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.0/dist/echo.iife.js');
                    } catch {
                        await loadScript('https://unpkg.com/laravel-echo@1.16.0/dist/echo.iife.js');
                    }
                }

                const EchoCtor = window.Echo; // constructor global
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

                // crea instancia si aún no existe (o si no tiene .private)
                if (!window.__echoInstance || !window.__echoInstance.private) {
                    window.__echoInstance = new EchoCtor({
                        broadcaster: 'reverb',
                        key: @json(env('REVERB_APP_KEY')),
                        wsHost: @json(env('REVERB_HOST', request()->getHost())),
                        wsPort: Number(@json(env('REVERB_PORT', 80))),
                        wssPort: Number(@json(env('REVERB_PORT', 443))),
                        forceTLS: @json(env('REVERB_SCHEME', 'https') === 'https'),
                        enabledTransports: ['ws', 'wss'],
                        authEndpoint: '/broadcasting/auth',
                        auth: {
                            headers: {
                                'X-CSRF-TOKEN': csrf
                            }
                        },
                        withCredentials: true,
                    });
                }
                window.Echo = window.__echoInstance;
            }

            async function subscribeOnce() {
                await ensureEcho();

                // mapa global de suscripciones para no repetir
                window.__echoSubs = window.__echoSubs || {};
                if (window.__echoSubs[convId]) return; // ya suscrito

                console.log('Echo listo. Suscribiendo a conversations.' + convId);

                window.Echo
                    .private(`conversations.${convId}`)
                    .listen('.message.created', (e) => {
                        console.log('Recibido message.created', e);
                        window.Livewire?.dispatch('realtime-message-received', {
                            id: e.id
                        });
                    });

                window.__echoSubs[convId] = true;
            }

            // suscribe una sola vez
            subscribeOnce();
        })();
    </script>
@endpush
