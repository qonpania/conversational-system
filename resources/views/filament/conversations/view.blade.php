{{-- resources/views/filament/conversations/view.blade.php --}}
@php
    use Illuminate\Support\Str;
    /** @var \App\Models\Conversation $record */
    $metrics = $this->record->metrics; // relación 1–1 ya cargada en resolveRecord()
@endphp

<x-filament::page>
    <div x-data="conversationUI({ convId: @js($this->record->id) })" x-init="initEcho();
    initScroll();" class="grid grid-cols-1 lg:grid-cols-12 gap-4" x-cloak>
        {{-- Columna izquierda: Contexto y estado --}}
        <aside class="lg:col-span-3 space-y-4">
            <x-filament::section>
                <x-slot name="heading">Contacto</x-slot>

                <div class="space-y-1 text-sm">
                    <div class="font-medium">
                        {{ $this->record->contact->username ? '@' . $this->record->contact->username : $this->record->contact->name ?? 'Contacto' }}
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">
                        Canal: {{ $this->record->channel->name }} • {{ strtoupper($this->record->status) }}
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">
                        Último:
                        {{ optional($this->record->last_message_at)->tz(config('app.timezone'))->format('Y-m-d H:i') }}
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($metrics)
                        <x-filament::badge :color="$metrics->sentiment_overall === 'negative' ? 'danger' : ($metrics->sentiment_overall === 'positive' ? 'success' : 'gray')">
                            Sent: {{ strtoupper($metrics->sentiment_overall) }}
                            ({{ number_format($metrics->sentiment_score, 2) }})
                        </x-filament::badge>
                        <x-filament::badge color="gray">Trend:
                            {{ $metrics->sentiment_trend ?? '—' }}</x-filament::badge>
                        <x-filament::badge color="gray">Msgs: {{ $metrics->message_count }}</x-filament::badge>
                    @else
                        <x-filament::badge color="gray">Sin métricas</x-filament::badge>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Resumen</x-slot>

                <template x-if="$store.summary.loading">
                    <div class="space-y-2 animate-pulse">
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-11/12"></div>
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-10/12"></div>
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-8/12"></div>
                    </div>
                </template>

                <div x-show="!$store.summary.loading">
                    @if ($this->record->summary)
                        <p class="text-sm leading-relaxed whitespace-pre-line text-gray-800 dark:text-gray-100">
                            {{ $this->record->summary }}
                        </p>
                        <div class="text-xs mt-2 text-gray-500 dark:text-gray-400">
                            {{ $this->record->summary_updated_at?->tz(config('app.timezone'))?->format('Y-m-d H:i') }}
                            @if ($meta = $this->record->summary_meta)
                                · Modelo: {{ $meta['model'] ?? '—' }} · Tokens: {{ $meta['tokens'] ?? '—' }}
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Aún no hay resumen.</p>
                    @endif
                </div>

                <div class="mt-3 flex gap-2">
                    <x-filament::button wire:click="regenerateSummary" wire:loading.attr="disabled"
                        wire:target="regenerateSummary" x-on:click="$store.summary.loading = true"
                        icon="heroicon-o-arrow-path">
                        <span wire:loading.remove wire:target="regenerateSummary">Regenerar resumen</span>
                        <span wire:loading wire:target="regenerateSummary">Generando…</span>
                    </x-filament::button>

                    <x-filament::button color="gray" icon="heroicon-o-chart-bar"
                        x-on:click="$dispatch('open-panel-analytics')">
                        Ver analytics
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recomendaciones</x-slot>

                {{-- Loader skeleton --}}
                <template x-if="$store.reco.loading">
                    <div class="space-y-2 animate-pulse">
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-11/12"></div>
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-10/12"></div>
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-9/12"></div>
                        <div class="h-3 rounded bg-gray-200 dark:bg-gray-700 w-7/12"></div>
                    </div>
                </template>

                <div x-show="!$store.reco.loading">
                    @if ($this->record->recommendations)
                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            {!! \Illuminate\Support\Str::markdown($this->record->recommendations) !!}
                        </div>
                        <div class="text-xs mt-2 text-gray-500 dark:text-gray-400">
                            {{ $this->record->recommendations_updated_at?->tz(config('app.timezone'))?->format('Y-m-d H:i') }}
                            @if ($meta = $this->record->recommendations_meta)
                                · Modelo: {{ $meta['model'] ?? '—' }} · Tokens: {{ $meta['tokens'] ?? '—' }}
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Aún no hay recomendaciones.</p>
                    @endif
                </div>

                <div class="mt-3 flex gap-2">
                    <x-filament::button icon="heroicon-o-light-bulb"
                        x-on:click="$store.reco.loading = true; $wire.generateRecommendations()">
                        Generar recomendaciones
                    </x-filament::button>

                    @if ($this->record->recommendations)
                        {{-- 👇 Pasamos el texto a copiar seguro en data-* codificado en Base64 --}}
                        <x-filament::button x-data="{ copied: false }" :color="$this->record->recommendations ? 'gray' : 'gray'"
                            data-reco="{{ base64_encode($this->record->recommendations ?? '') }}"
                            x-on:click="
    const txt = window.decodeB64Utf8($el.dataset.reco);
    navigator.clipboard.writeText(txt)
      .then(() => {
        copied = true;
        // (opcional) también dispara toast:
        $dispatch('filament-notify', { status: 'success', message: 'Copiado al portapapeles' });
        setTimeout(() => copied = false, 1600);
      })
      .catch(() => {
        $dispatch('filament-notify', { status: 'danger', message: 'No se pudo copiar' });
      });
  "
                            class="relative">
                            <span x-show="!copied" class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-o-clipboard" class="w-4 h-4" />
                                <span>Copiar</span>
                            </span>

                            <span x-show="copied" x-cloak class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-o-check" class="w-4 h-4" />
                                <span>¡Copiado!</span>
                            </span>
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>



            {{-- Plantillas rápidas (canned responses) --}}
            @if ($this->record->routing_mode === 'human')
                <x-filament::section>
                    <x-slot name="heading">Plantillas</x-slot>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['¿Podrías confirmarme tu número de cliente, por favor?', 'Estamos revisando tu caso, te aviso en breve.', 'Hemos ajustado tu recibo; verás el cambio en 24-48h.'] as $tpl)
                            <x-filament::button color="gray" size="sm"
                                x-on:click="$dispatch('fill-admin-text', { text: {{ Js::from($tpl) }} })">
                                {{ Str::limit($tpl, 28) }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </aside>

        {{-- Columna central: Chat --}}
        <section class="lg:col-span-6 flex flex-col min-h-[70vh]">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Conversación • {{ $this->record->id }}
                </div>
                <div class="flex gap-2">
                    @if ($this->record->routing_mode !== 'human')
                        <x-filament::badge color="primary">Modo IA</x-filament::badge>
                    @else
                        <x-filament::badge color="warning">Modo HUMANO</x-filament::badge>
                    @endif
                </div>
            </div>

            {{-- Contenedor de mensajes --}}
            <div id="chatScroll"
                class="flex-1 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 overflow-y-auto">
                {{-- Loader de “cargar más” arriba (paginación futura) --}}
                <div id="loadMore" class="flex justify-center my-2">
                    <x-filament::badge color="gray" size="sm">Desliza arriba para cargar más…</x-filament::badge>
                </div>

                @foreach ($this->record->messages as $m)
                    @php
                        $isOutbound = $m->direction === 'outbound';
                        $a = $m->analytics; // relación 1–1
                    @endphp

                    <div class="mb-3 flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
                        <div
                            class="max-w-[78%] rounded-xl px-4 py-2 shadow
                            {{ $isOutbound
                                ? 'bg-primary-600 text-white rounded-br-none'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-bl-none' }}">
                            @if ($m->type === 'text')
                                <div class="whitespace-pre-line text-[15px] leading-relaxed">
                                    {{ $m->text }}
                                </div>
                            @else
                                <div class="text-xs opacity-80 mb-1">
                                    ({{ strtoupper($m->type) }})
                                </div>

                                @if ($m->attachments)
                                    @foreach ($m->attachments as $aFile)
                                        @if (Str::of($aFile['mime'] ?? '')->startsWith('image/'))
                                            <img src="{{ $aFile['url'] ?? '' }}"
                                                alt="{{ $aFile['filename'] ?? 'image' }}"
                                                class="rounded-lg mt-2 border border-gray-200 dark:border-gray-700">
                                        @else
                                            <a href="{{ $aFile['url'] ?? '#' }}" target="_blank"
                                                class="underline text-xs break-all text-blue-700 dark:text-blue-400">
                                                {{ $aFile['filename'] ?? 'archivo' }} ({{ $aFile['mime'] ?? 'file' }})
                                            </a>
                                        @endif
                                    @endforeach
                                @endif
                            @endif

                            {{-- Badges de análisis por mensaje (usa badges de Filament) --}}
                            <div class="flex items-center gap-1 mt-1">
                                @if ($a?->pii_flag)
                                    <x-filament::badge size="sm" color="warning">PII</x-filament::badge>
                                @endif

                                @if ($a?->toxicity_flag)
                                    <x-filament::badge size="sm" color="danger">Toxic</x-filament::badge>
                                @endif

                                @if ($a?->abuse_flag)
                                    <x-filament::badge size="sm" color="danger">Abuse</x-filament::badge>
                                @endif

                                @if ($a?->sentiment)
                                    @php
                                        $sentColor =
                                            $a->sentiment === 'positive'
                                                ? 'success'
                                                : ($a->sentiment === 'negative'
                                                    ? 'danger'
                                                    : 'gray');
                                    @endphp
                                    <x-filament::badge size="sm" :color="$sentColor">
                                        {{ $a->sentiment }}{{ filled($a->sentiment_score) ? ' ' . number_format($a->sentiment_score, 2) : '' }}
                                    </x-filament::badge>
                                @endif
                            </div>

                            <div
                                class="mt-1 text-[10px] opacity-70 {{ $isOutbound ? 'text-white' : 'text-gray-600 dark:text-gray-400' }} text-right">
                                {{ optional($m->sent_at)->tz(config('app.timezone'))->format('Y-m-d H:i') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Composer (solo en modo humano) --}}
            @if ($this->record->routing_mode === 'human')
                <form wire:submit.prevent="sendAdmin" class="mt-3">
                    <div class="flex items-end gap-2">
                        <textarea id="adminText" wire:model.defer="adminText" rows="2"
                            placeholder="Escribe tu mensaje… (Ctrl+Enter envía)"
                            class="flex-1 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            x-on:keydown.ctrl.enter="$wire.sendAdmin()"></textarea>
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" class="shrink-0">
                            Enviar
                        </x-filament::button>
                    </div>
                </form>
            @endif
        </section>

        {{-- Columna derecha: Analytics & acciones rápidas --}}
        <aside class="lg:col-span-3 space-y-4" x-data
            @open-panel-analytics.window="$el.scrollIntoView({behavior:'smooth'})">
            <x-filament::section>
                <x-slot name="heading">Analytics</x-slot>

                @if ($metrics)
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">CSAT (pred)</dt>
                            <dd class="font-semibold">
                                {{ isset($metrics->csat_pred) ? number_format($metrics->csat_pred * 100, 0) . '%' : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Churn</dt>
                            <dd class="font-semibold">
                                {{ isset($metrics->churn_risk) ? number_format($metrics->churn_risk * 100, 0) . '%' : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">FCR</dt>
                            <dd class="font-semibold">{{ $metrics->fcr ? 'Sí' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">AHT</dt>
                            <dd class="font-semibold">
                                {{ $metrics->avg_response_time ? $metrics->avg_response_time . 's' : '—' }}
                            </dd>
                        </div>
                    </dl>

                    @if (is_array($metrics->top_intents) && count($metrics->top_intents))
                        <div class="mt-3">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Top intents</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($metrics->top_intents as $ti)
                                    <x-filament::badge color="gray">
                                        {{ $ti['label'] ?? '—' }} ({{ $ti['count'] ?? 0 }})
                                    </x-filament::badge>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-sm text-gray-500 dark:text-gray-400">Aún sin analytics.</div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Enrutamiento</x-slot>
                <div class="flex flex-col gap-2">
                    @if ($this->record->routing_mode !== 'human')
                        <x-filament::button color="warning" icon="heroicon-o-user"
                            wire:click="$dispatch('open-modal', { id: 'takeover' })">
                            Tomar (Humano)
                        </x-filament::button>
                    @else
                        <x-filament::button color="success" icon="heroicon-o-cpu-chip"
                            wire:click="$dispatch('open-modal', { id: 'resume-ai' })">
                            Volver a IA
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        </aside>
    </div>

    {{-- Modales --}}
    <x-filament::modal id="takeover" width="md" :slide-over="false">
        <x-slot name="heading">Tomar conversación</x-slot>
        <div class="text-sm text-gray-600 dark:text-gray-300">¿Deseas pasar a modo HUMANO?</div>
        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'takeover' })">
                Cancelar
            </x-filament::button>

            <x-filament::button color="warning"
                x-on:click="$wire.takeover().then(() => $dispatch('close-modal', { id: 'takeover' }))">
                Confirmar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="resume-ai" width="md" :slide-over="false">
        <x-slot name="heading">Volver a IA</x-slot>
        <div class="text-sm text-gray-600 dark:text-gray-300">La IA retomará la conversación.</div>
        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'resume-ai' })">
                Cancelar
            </x-filament::button>

            <x-filament::button color="success"
                x-on:click="$wire.resumeAi().then(() => $dispatch('close-modal', { id: 'resume-ai' }))">
                Confirmar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament::page>

@push('scripts')
    <script data-navigate-once>
        window.decodeB64Utf8 = (b64) => {
            const bin = atob(b64);
            const bytes = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
            return new TextDecoder('utf-8').decode(bytes);
        };

        window.addEventListener('copy-text', async (ev) => {
            try {
                await navigator.clipboard.writeText(ev.detail?.text ?? '');
                window.dispatchEvent(new CustomEvent('filament-notify', {
                    detail: {
                        status: 'success',
                        message: 'Recomendaciones copiadas'
                    }
                }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('filament-notify', {
                    detail: {
                        status: 'danger',
                        message: 'No se pudo copiar'
                    }
                }));
            }
        });

        window.addEventListener('fill-admin-text', (ev) => {
            const t = document.getElementById('adminText');
            if (!t) return;
            t.value = ev.detail?.text ?? '';
            // Notifica a Livewire que el campo cambió
            t.dispatchEvent(new Event('input', {
                bubbles: true
            }));
            t.focus();
        });

        document.addEventListener('alpine:init', () => {
            Alpine.store('summary', {
                loading: false
            });

            Alpine.store('reco', {
                loading: false
            });
        });

        function conversationUI({
            convId
        }) {
            return {
                convId,
                echoReady: false,
                atBottom: true,
                // ---- BOOTSTRAP ECHO (trae script y crea instancia si falta) ----
                async ensureEcho() {
                    // 1) Carga el bundle IIFE si no está
                    if (typeof window.Echo === 'undefined' || (typeof window.Echo === 'function' && !window
                            .__echoInstance)) {
                        const loadScript = (src) => new Promise((res, rej) => {
                            const s = document.createElement('script');
                            s.src = src;
                            s.async = true;
                            s.onload = res;
                            s.onerror = rej;
                            document.head.appendChild(s);
                        });
                        try {
                            await loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.0/dist/echo.iife.js');
                        } catch {
                            await loadScript('https://unpkg.com/laravel-echo@1.16.0/dist/echo.iife.js');
                        }
                    }

                    // 2) Crea instancia si no existe o si le falta .private
                    if (!window.__echoInstance || !window.__echoInstance.private) {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                        const EchoCtor = window.Echo; // el constructor global del bundle IIFE

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
                },

                async initEcho() {
                    // Construye si falta y suscribe una sola vez
                    await this.ensureEcho();
                    if (this.echoReady) return;

                    // Evita doble suscripción en navegación/Livewire re-renders
                    window.__echoSubs = window.__echoSubs || {};
                    if (window.__echoSubs[this.convId]) {
                        this.echoReady = true;
                        return;
                    }

                    console.log('Echo listo. Suscribiendo a conversations.' + this.convId);

                    window.Echo
                        .private(`conversations.${this.convId}`)
                        .listen('.message.created', () => {
                            if (this.atBottom) this.scrollToBottom();
                            window.Livewire?.dispatch('realtime-message-received');
                        })
                        .listen('.summary.updated', (e) => {
                            // llegó el broadcast -> actualiza y apaga spinner
                            Alpine.store('summary').loading = false;
                            window.Livewire?.dispatch('summary-updated', {
                                summary: e.summary,
                                summary_meta: e.summary_meta,
                                updated_at: e.updated_at
                            });
                        })
                        .listen('.analytics.updated', () => {
                            window.Livewire?.dispatch('realtime-message-received');
                        })
                        .listen('.recommendations.updated', (e) => {
                            Alpine.store('reco').loading = false;
                            window.Livewire?.dispatch('recommendations-updated', {
                                recommendations: e.recommendations,
                                recommendations_meta: e.recommendations_meta,
                                updated_at: e.updated_at
                            });
                        });

                    // Fallback: si en 15s no llegó el broadcast, forzar refresh 1 vez
                    window.__summaryFallbackTimer?.clear?.();
                    window.__summaryFallbackTimer = setTimeout(() => {
                        if (Alpine.store('summary').loading) {
                            // Llama a un método Livewire ligero que recargue summary desde BD
                            $wire.call('refreshSummaryOnce')
                                .then(() => {
                                    Alpine.store('summary').loading = false;
                                })
                                .catch(() => {
                                    Alpine.store('summary').loading = false;
                                });
                        }
                    }, 15000);

                    window.__recoFallbackTimer?.clear?.();
                    window.__recoFallbackTimer = setTimeout(() => {
                        if (Alpine.store('reco').loading) {
                            $wire.call('refreshRecommendationsOnce')
                                .then(() => {
                                    Alpine.store('reco').loading = false;
                                })
                                .catch(() => {
                                    Alpine.store('reco').loading = false;
                                });
                        }
                    }, 15000);

                    window.__echoSubs[this.convId] = true;
                    this.echoReady = true;
                },

                initScroll() {
                    const el = document.getElementById('chatScroll');
                    if (!el) return;

                    const onScroll = () => {
                        const nearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 40;
                        this.atBottom = nearBottom;
                    };
                    el.addEventListener('scroll', onScroll);

                    const sentinel = document.getElementById('loadMore');
                    if (sentinel && 'IntersectionObserver' in window) {
                        const io = new IntersectionObserver((entries) => {
                            entries.forEach((en) => {
                                if (en.isIntersecting) {
                                    // $wire.loadMore()  // si más adelante paginas
                                }
                            });
                        }, {
                            root: el,
                            threshold: 1.0
                        });
                        io.observe(sentinel);
                    }

                    this.scrollToBottom();
                },

                scrollToBottom() {
                    const el = document.getElementById('chatScroll');
                    if (!el) return;
                    el.scrollTo({
                        top: el.scrollHeight,
                        behavior: 'smooth'
                    });
                },
            }
        }
    </script>
@endpush
