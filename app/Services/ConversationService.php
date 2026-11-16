<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function start(User $sender, array $data): Conversation
    {
        $project = Project::findOrFail($data['project_id']);
        $recipient = User::findOrFail($data['recipient_id']);
        if ($sender->is($recipient)) {
            throw ValidationException::withMessages(['recipient_id' => 'Choose another person.']);
        }

        abort_unless(
            $sender->isAdmin()
            || ($sender->id === $project->client_id && ($recipient->isFreelancer() || $recipient->isAdmin()))
            || ($sender->isFreelancer() && $recipient->id === $project->client_id),
            403
        );

        $type = $data['context_type'] ?? 'project';
        $contextId = $data['context_id'] ?? $project->id;
        if ($type === 'project') {
            abort_unless((int) $contextId === $project->id, 404);
        } else {
            // shortcut: other contexts require their module models and view policies before they can be used.
            $model = ['task' => 'App\\Models\\Task', 'proposal' => 'App\\Models\\Proposal', 'review' => 'App\\Models\\Review'][$type];
            if (! class_exists($model)) {
                throw ValidationException::withMessages(['context_type' => 'This conversation context is not available yet.']);
            }
            $context = $model::where('project_id', $project->id)->findOrFail($contextId);
            Gate::forUser($sender)->authorize('view', $context);
            Gate::forUser($recipient)->authorize('view', $context);
        }

        return DB::transaction(function () use ($sender, $recipient, $project, $type, $contextId, $data) {
            $ids = [$sender->id, $recipient->id];
            sort($ids);
            $conversation = Conversation::firstOrCreate([
                'project_id' => $project->id, 'context_type' => $type, 'context_id' => $contextId,
                'user_one_id' => $ids[0], 'user_two_id' => $ids[1],
            ]);
            $this->send($conversation, $sender, $data['body']);

            return $conversation;
        });
    }

    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        abort_unless($conversation->hasParticipant($sender), 404);

        return DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create(['user_id' => $sender->id, 'body' => $body]);
            $conversation->touch();
            app(NotificationService::class)->send(
                $conversation->otherUser($sender), 'messages',
                "{$sender->name} sent a message about {$conversation->project->title}",
                route('messages.show', $conversation, absolute: false), "message:{$message->id}"
            );

            return $message;
        });
    }
}
