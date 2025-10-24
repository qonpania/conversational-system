<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use App\Filament\Resources\ConversationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected static string $view = 'filament.conversations.view';

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
}
