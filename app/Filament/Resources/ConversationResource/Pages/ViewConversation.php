<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use App\Filament\Resources\ConversationResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected static string $view = 'filament.conversations.view';

    protected $listeners = ['realtime-message-received' => 'refreshMessages'];

    public bool $summaryPending = false;

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

    public string $adminText = '';

    public function sendAdmin(): void
    {
        $this->validate(['adminText' => ['required','string','max:4000']]);

        $url = config('services.n8n.admin_outbound_webhook');
        if (! $url) {
            Notification::make()->title('Webhook n8n no configurado')->danger()->send();
            return;
        }

        try {
            // 1) Dispara a n8n (con timeouts bajos para no bloquear la UI)
            $resp = Http::asJson()
                ->timeout(5)           // ⬅ evita esperar 30s
                ->connectTimeout(2)
                // ->withHeaders(['X-Api-Key' => config('services.prompt_api.key')])
                ->post($url, [
                    'conversation_id' => $this->record->id,
                    'channel' => $this->record->channel->driver, // "telegram"
                    'contact' => [
                        'external_id' => $this->record->contact->external_id,
                        'username'    => $this->record->contact->username,
                        'name'        => $this->record->contact->name,
                    ],
                    'text' => $this->adminText,
                ]);

            // 2) Opcional: si n8n responde con el message_id, guárdalo; si no, igual registramos el OUTBOUND local.
            $tgMessageId = data_get($resp->json(), 'result.message_id');

            DB::transaction(function () use ($tgMessageId) {
                $msg = $this->record->messages()->create([
                    'direction'   => 'outbound',
                    'type'        => 'text',
                    'text'        => $this->adminText,
                    'payload'     => $tgMessageId ? ['raw'=>['telegram'=>['message_id'=>$tgMessageId]]] : null,
                    'attachments' => null,
                    'sent_at'     => now(),
                ]);
                $this->record->update(['last_message_at' => $msg->sent_at]);
            });

            $this->adminText = '';
            Notification::make()->title('Mensaje enviado (procesando en n8n)')->success()->send();
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Error enviando')
                ->body(str($e->getMessage())->limit(160))   
                ->danger()
                ->send();
        }
    }

}
