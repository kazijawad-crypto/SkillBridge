<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['project_id', 'context_type', 'context_id', 'user_one_id', 'user_two_id'];

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id));
    }

    public function hasParticipant(User $user): bool
    {
        return in_array($user->id, [$this->user_one_id, $this->user_two_id], true);
    }

    public function otherUser(User $user): User
    {
        return $this->user_one_id === $user->id ? $this->secondUser : $this->firstUser;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function firstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function secondUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->chaperone();
    }
}
