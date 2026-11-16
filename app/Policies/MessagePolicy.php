<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function update(User $user, Message $message): bool
    {
        return $message->user_id === $user->id && $message->conversation->hasParticipant($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }
}
