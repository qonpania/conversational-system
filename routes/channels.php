<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversations.{conversationId}', function ($user, $conversationId) {
    // autoriza si el usuario puede ver esa conversación
    $conversation = Conversation::find($conversationId);
    return $conversation && $user->can('view', $conversation);
});

