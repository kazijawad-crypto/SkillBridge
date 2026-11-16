<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): Response
    {
        return $conversation->hasParticipant($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
