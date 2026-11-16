<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-deadlines')]
#[Description('Notify project clients and conversation participants about deadlines today or tomorrow')]
class SendDeadlineNotifications extends Command
{
    public function handle(NotificationService $notifications): int
    {
        $today = today();
        $tomorrow = today()->addDay();
        Project::whereNotIn('status', ['closed', 'completed', 'cancelled'])
            ->whereDate('deadline', '>=', $today)->whereDate('deadline', '<=', $tomorrow)
            ->each(function (Project $project) use ($notifications) {
                $this->notifyProject($project, $notifications, "Project deadline: {$project->title}", 'project', $project->id, $project->deadline->toDateString());
            });

        Milestone::with('project')->whereNotIn('status', ['completed'])
            ->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', $tomorrow)
            ->whereHas('project', fn ($query) => $query->whereNotIn('status', ['closed', 'completed', 'cancelled']))
            ->each(function (Milestone $milestone) use ($notifications) {
                $this->notifyProject($milestone->project, $notifications, "Milestone deadline: {$milestone->title}", 'milestone', $milestone->id, $milestone->due_date->toDateString());
            });

        $this->info('Deadline notifications processed.');

        return self::SUCCESS;
    }

    private function notifyProject(Project $project, NotificationService $notifications, string $title, string $kind, int $id, string $date): void
    {
        // shortcut: project conversations identify collaborators until team membership is implemented.
        $conversations = Conversation::where('project_id', $project->id)->get(['user_one_id', 'user_two_id']);
        $userIds = $conversations->pluck('user_one_id')->merge($conversations->pluck('user_two_id'))->push($project->client_id)->unique();
        User::whereIn('id', $userIds)->each(function (User $user) use ($project, $notifications, $title, $kind, $id, $date) {
            $url = $kind === 'milestone'
                ? route('projects.milestones.index', $project, absolute: false)
                : route('projects.show', $project, absolute: false);
            $notifications->send($user, 'deadlines', $title, $url, "{$kind}:{$id}:{$date}");
        });
    }
}
