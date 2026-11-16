<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    public const TYPES = [
        'messages' => 'New messages',
        'proposals' => 'Proposal updates',
        'task_assignments' => 'Task assignments',
        'review_requests' => 'Review requests',
        'deadlines' => 'Upcoming deadlines',
    ];

    protected $fillable = ['messages', 'proposals', 'task_assignments', 'review_requests', 'deadlines'];

    protected function casts(): array
    {
        return array_fill_keys(array_keys(self::TYPES), 'boolean');
    }
}
