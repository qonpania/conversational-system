<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use App\Filament\Resources\ConversationResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rules\File;

class ViewConversation extends Page
{
    use InteractsWithRecord;
    use WithFileUploads;

    protected static string $resource = ConversationResource::class;

    protected static string $view = 'filament.conversations.view';

    protected $listeners = ['realtime-message-received' => 'refreshMessages'];

    public bool $summaryPending = false;

    public bool $recoPending = false;

     /** Texto */
    public string $adminText = '';

    /** Subidas (arrastrar/soltar, botón clip) */
    public array $uploads = [];           // Livewire temp files

    /** Audio grabado (blob convertido a File) */
    public $audioUpload = null;

        protected function rules(): array
    {
        return [
            'adminText' => ['nullable','string','max:4000'],
            'uploads.*' => [
                'file',
                File::types([
                    'jpg','jpeg','png','webp','gif',
                    'mp4','webm','mp3','ogg','wav',
                    'pdf','docx','xlsx','zip','txt'
                ])->max(32 * 1024) // 32 MB
            ],
            'audioUpload' => [
                'nullable','file',
                File::types(['webm','ogg','mp3','wav'])->max(16 * 1024)
            ],
        ];
    }

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    #[On('recommendations-updated')]
    public function onRecommendationsUpdated($payload = []): void
    {
        $this->record->recommendations = $payload['recommendations'] ?? $this->record->recommendations;
        $this->record->recommendations_meta = $payload['recommendations_meta'] ?? $this->record->recommendations_meta;
        $this->record->recommendations_updated_at = \Illuminate\Support\Carbon::parse($payload['updated_at'] ?? now());
        $this->recoPending = false;
        $this->dispatch('$refresh');
    }

    public function refreshRecommendationsOnce(): void
    {
        $this->record->refresh();
        $this->dispatch('$refresh');
        $this->recoPending = false;
    }

    public function generateRecommendations(): void
    {
        $url = config('services.n8n.recommendations_webhook'); // define N8N_RECOMMENDATIONS_WEBHOOK en .env

        if (! $url) {
            Notification::make()
                ->title('Webhook de n8n no configurado')
                ->body('Define N8N_RECOMMENDATIONS_WEBHOOK en tu .env.')
                ->danger()->send();
            return;
        }

        $this->recoPending = true;

        try {
            Http::asJson()
                ->timeout(5)->connectTimeout(2)
                ->post($url, [
                    'conversation_id' => $this->record->id,
                    'limit' => 60, // ventana de mensajes
                    // Puedes enviar señales útiles:
                    'analytics' => [
                        'sentiment_overall' => $this->record->metrics->sentiment_overall ?? null,
                        'top_intents'       => $this->record->metrics->top_intents ?? [],
                        'csat_pred'         => $this->record->metrics->csat_pred ?? null,
                        'churn_risk'        => $this->record->metrics->churn_risk ?? null,
                    ],
                ]);

            Notification::make()
                ->title('Generando recomendaciones')
                ->body('Solicitud enviada a n8n. Se actualizará automáticamente.')
                ->success()->send();
        } catch (\Throwable $e) {
            report($e);
            $this->recoPending = false;

            Notification::make()
                ->title('Error al solicitar recomendaciones')
                ->body(str($e->getMessage())->limit(160))
                ->danger()->send();
        }
    }

    #[On('realtime-message-received')]
    public function refreshMessages(): void
    {
        // Vuelve a cargar las relaciones y refresca la vista
        $this->record->refresh();
        $this->record->load(['messages','contact','channel']);
        $this->dispatch('$refresh');
    }

    #[On('summary-updated')]
    public function onSummaryUpdated($payload = []): void
    {
        // Actualiza el record en memoria (sin ir a la BD)
        $this->record->summary = $payload['summary'] ?? $this->record->summary;
        $this->record->summary_meta = $payload['summary_meta'] ?? $this->record->summary_meta;
        $this->record->summary_updated_at = \Illuminate\Support\Carbon::parse($payload['updated_at'] ?? now());

        $this->summaryPending = false;

        $this->dispatch('$refresh'); // re-render
    }

    public function takeover(): void
    {
        $this->record->update([
            'routing_mode'     => 'human',
            'assigned_user_id' => Auth::id(),
            'handover_at'      => now(),
        ]);

        // refresca el modelo en memoria y re-renderiza
        $this->record->refresh();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Tomaste la conversación')
            ->success()
            ->send();
    }

    public function resumeAi(): void
    {
        $this->record->update([
            'routing_mode'     => 'ai',
            'assigned_user_id' => null,
            'resume_ai_at'     => now(),
        ]);

        $this->record->refresh();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('La IA retomó la conversación')
            ->success()
            ->send();
    }

    // Debe ser público
    public function getTitle(): string
    {
        $c = $this->record->contact;
        return 'Conversación con ' . ($c->username ?? $c->name ?? 'Contacto');
    }

    protected function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        /** @var \App\Models\Conversation $record */
        $record = static::getModel()::query()
            ->with(['messages','contact','channel'])
            ->findOrFail($key);

        return $record;
    }

    public function regenerateSummary(): void
    {
        $url = config('services.n8n.summarize_webhook');

        if (! $url) {
            Notification::make()
                ->title('Webhook de n8n no configurado')
                ->body('Define N8N_SUMMARIZE_WEBHOOK en tu .env.')
                ->danger()
                ->send();
            return;
        }

        $this->summaryPending = true;

        try {
            Http::asJson()
                // ->withHeaders(['X-Api-Key' => config('services.prompt_api.key')]) // opcional
                ->post($url, [
                    'conversation_id' => $this->record->id,
                    'limit' => 80,
                ])
                ->throw();

            Notification::make()
                ->title('Resumen en proceso')
                ->body('Solicitud enviada a n8n. Refresca en unos segundos.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Error al solicitar resumen')
                ->body(str($e->getMessage())->limit(160))
                ->danger()
                ->send();
            $this->summaryPending = false;
        }
    }

    /** Enviar mensaje con texto + adjuntos (y opcional audio) */
    public function sendAdmin(): void
    {
        // Evita mensajes vacíos sin archivos
        if ($this->adminText === '' && empty($this->uploads) && !$this->audioUpload) {
            Notification::make()->title('Nada para enviar')->danger()->send();
            return;
        }

        $this->validate();

        $msg = null;

        DB::transaction(function () use (&$msg) {
            // 1) Crear mensaje base
            $msg = $this->record->messages()->create([
                'direction' => 'outbound',
                'type'      => $this->resolveMessageType(),
                'text'      => $this->adminText ?: null,
                'sent_at'   => now(),
            ]);

            // 2) Adjuntar media
            foreach ($this->uploads as $tmp) {
                $msg->addMedia($tmp->getRealPath())
                    ->usingFileName($tmp->getClientOriginalName())
                    ->toMediaCollection('attachments');
            }
            if ($this->audioUpload) {
                $msg->addMedia($this->audioUpload->getRealPath())
                    ->usingFileName($this->audioUpload->getClientOriginalName() ?: 'voice.webm')
                    ->toMediaCollection('attachments');
            }
            // 3) Actualizar last_message_at
            $this->record->update(['last_message_at' => $msg->sent_at]);
        });

        // Dispara webhook n8n (opcional: pasa URLs firmadas a Telegram/WhatsApp)
        $this->notifyOutboundViaN8n($msg);

        // Limpia estado UI
        $this->reset(['adminText','uploads','audioUpload']);
        $this->dispatch('$refresh');
        Notification::make()->title('Mensaje enviado')->success()->send();
    }

    /** Heurística simple para “type” principal del mensaje */
    private function resolveMessageType(): string
    {
        $hasFiles = count($this->uploads) > 0 || $this->audioUpload;
        if (!$hasFiles) return 'text';

        // Si hay solo audio
        if ($this->audioUpload && count($this->uploads) === 0) return 'audio';

        // Si hay al menos una imagen y nada más, lo marcamos como “image”
        $first = $this->uploads[0] ?? $this->audioUpload;
        $mime  = $first?->getMimeType() ?? 'application/octet-stream';

        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';

        return 'file';
    }

    /** Envía a n8n: texto + enlaces firmados a media (para Telegram/WA) */
    private function notifyOutboundViaN8n(\App\Models\Message $msg): void
    {
        $url = config('services.n8n.admin_outbound_webhook');
        if (!$url) return;

        try {

            $media = $msg->getMedia('attachments');

            // Crea URLs temporales públicas (si usas S3, usa temporaryUrl)
            $files = $media->map(function ($m) {
                return [
                    'name' => $m->file_name,
                    'mime' => $m->mime_type,
                    'url'  => $m->getFullUrl(), // si disco public; con S3 usa $m->getTemporaryUrl(...)
                ];
            })->values()->all();

            Http::asJson()->timeout(5)->connectTimeout(2)->post($url, [
                'conversation_id' => $this->record->id,
                'channel' => $this->record->channel->driver, // p.ej. "telegram"
                'contact' => [
                    'external_id' => $this->record->contact->external_id,
                    'username'    => $this->record->contact->username,
                    'name'        => $this->record->contact->name,
                ],
                'text'  => $msg->text,
                'files' => $files, // n8n decide si manda photo/document/audio/voice
                'message_type' => $msg->type,
            ]);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Enviado local, fallo webhook n8n')
                ->body(str($e->getMessage())->limit(140))->warning()->send();
        }
    }
}
