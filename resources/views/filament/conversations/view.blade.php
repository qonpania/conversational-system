{{-- @php use Illuminate\Support\Str; @endphp --}}

<x-filament::page>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->record->channel->name }} • {{ strtoupper($this->record->status) }}
                </div>
                <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $this->record->contact->username ? '@'.$this->record->contact->username : ($this->record->contact->name ?? 'Contacto') }}
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
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 h-[70vh] overflow-y-auto">
            @foreach($this->record->messages as $m)
                @php
                    $isOutbound = $m->direction === 'outbound';
                @endphp

                <div class="mb-3 flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
                    <div
                        class="max-w-[75%] rounded-2xl px-4 py-2 shadow
                            {{ $isOutbound
                                ? 'bg-primary-600 text-white rounded-br-none'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-bl-none' }}"
                    >
                        @if($m->type === 'text')
                            <div class="whitespace-pre-line text-sm leading-relaxed">
                                {{ $m->text }}
                            </div>
                        @else
                            <div class="text-xs opacity-80 mb-1">
                                ({{ strtoupper($m->type) }})
                            </div>

                            @if($m->attachments)
                                @foreach($m->attachments as $a)
                                    @if(Str::of($a['mime'] ?? '')->startsWith('image/'))
                                        <img
                                            src="{{ $a['url'] ?? '' }}"
                                            alt="{{ $a['filename'] ?? 'image' }}"
                                            class="rounded-lg mt-2 border border-gray-200 dark:border-gray-700"
                                        >
                                    @else
                                        <a
                                            href="{{ $a['url'] ?? '#' }}"
                                            target="_blank"
                                            class="underline text-xs break-all text-blue-700 dark:text-blue-400"
                                        >
                                            {{ $a['filename'] ?? 'archivo' }} ({{ $a['mime'] ?? 'file' }})
                                        </a>
                                    @endif
                                @endforeach
                            @endif
                        @endif

                        <div class="mt-1 text-[10px] opacity-70 {{ $isOutbound ? 'text-white' : 'text-gray-600 dark:text-gray-400' }} text-right">
                            {{ optional($m->sent_at)->tz(config('app.timezone'))->format('Y-m-d H:i') }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament::page>
