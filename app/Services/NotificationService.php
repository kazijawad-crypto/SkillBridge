<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class NotificationService
{
    public function send(User $user, string $type, string $title, string $url, ?string $eventKey = null): ?DatabaseNotification
    {
        if (! array_key_exists($type, NotificationPreference::TYPES) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            throw new InvalidArgumentException('Notifications require a supported category and an internal link.');
        }

        if ($user->notificationPreference()->first()?->getAttribute($type) === false) {
            return null;
        }

        $id = $eventKey === null ? (string) Str::uuid() : (string) Uuid::uuid5(Uuid::NAMESPACE_URL, "skillbridge:{$user->id}:{$type}:{$eventKey}");

        return $user->notifications()->firstOrCreate(['id' => $id], [
            'type' => $type,
            'data' => ['title' => $title, 'url' => $url],
        ]);
    }
}
