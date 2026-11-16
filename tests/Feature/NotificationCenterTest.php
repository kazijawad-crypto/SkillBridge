<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_pages_require_authentication(): void
    {
        foreach (['/notifications', '/notifications/poll', '/notifications/count', '/notifications/preferences'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_notifications_are_private_and_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service = app(NotificationService::class);
        $own = $service->send($user, 'proposals', 'Your proposal update', '/projects/1');
        $foreign = $service->send($other, 'proposals', 'Other private update', '/projects/2');
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Your proposal update')->assertDontSee('Other private update');
        $this->get('/notifications/poll')->assertOk()->assertDontSee('Other private update');
        $this->getJson('/notifications/count')->assertExactJson(['unread' => 1]);
        $this->patch(route('notifications.read', $foreign->id))->assertNotFound();
        $this->patch(route('notifications.read', $own->id))->assertRedirect();
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->get('/notifications?filter=unread')->assertOk()->assertDontSee('Your proposal update');
        $this->getJson('/notifications/count')->assertExactJson(['unread' => 0]);
    }

    public function test_mark_all_only_affects_current_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service = app(NotificationService::class);
        $service->send($user, 'review_requests', 'Review requested', '/projects/1');
        $service->send($user, 'task_assignments', 'Task assigned', '/projects/1');
        $foreign = $service->send($other, 'messages', 'Private message', '/messages/1');
        $this->actingAs($user)->patch('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_preferences_default_to_enabled_and_only_update_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->get('/notifications/preferences')->assertOk()->assertSee('Notification preferences');
        $preferences = array_fill_keys(array_keys(NotificationPreference::TYPES), false);
        $this->patch('/notifications/preferences', $preferences + ['user_id' => $other->id])->assertRedirect(route('notifications.preferences'));
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id, 'messages' => false, 'deadlines' => false]);
        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $other->id]);
        $service = app(NotificationService::class);
        foreach (array_keys(NotificationPreference::TYPES) as $type) {
            $this->assertNull($service->send($user, $type, 'Disabled update', '/projects/1'));
        }
        $this->assertDatabaseCount('notifications', 0);
        $preferences['messages'] = true;
        $this->patch('/notifications/preferences', $preferences)->assertRedirect();
        $this->assertNotNull($service->send($user->fresh(), 'messages', 'Enabled update', '/messages/1'));
    }

    public function test_invalid_preferences_and_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->patchJson('/notifications/preferences', ['messages' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys(NotificationPreference::TYPES));
        $this->getJson('/notifications?filter=other')->assertUnprocessable()->assertJsonValidationErrors('filter');
    }

    public function test_every_notification_category_is_stored_and_event_keys_are_idempotent(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationService::class);
        foreach (array_keys(NotificationPreference::TYPES) as $type) {
            $first = $service->send($user, $type, 'Event '.$type, '/projects/1', 'event:1');
            $second = $service->send($user, $type, 'Event '.$type, '/projects/1', 'event:1');
            $this->assertSame($first->id, $second->id);
        }
        $this->assertSame(5, $user->notifications()->count());
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Event task_assignments')->assertSee('Event review_requests')->assertSee('Event proposals');
    }

    public function test_message_alert_preferences_do_not_block_delivery(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $freelancer = User::factory()->create(['role' => 'freelancer']);
        $freelancer->notificationPreference()->create(['messages' => false]);
        $project = Project::create(['client_id' => $client->id, 'title' => 'Quiet messages']);
        $this->actingAs($client)->post('/messages', ['project_id' => $project->id, 'recipient_id' => $freelancer->id, 'body' => 'Still delivered'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['body' => 'Still delivered']);
        $this->assertSame(0, $freelancer->notifications()->count());
    }

    public function test_deadline_job_notifies_collaborators_once_and_ignores_finished_or_distant_work(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $client = User::factory()->create(['role' => 'client']);
        $freelancer = User::factory()->create(['role' => 'freelancer']);
        $outsider = User::factory()->create(['role' => 'freelancer']);
        $project = Project::create(['client_id' => $client->id, 'title' => 'Due tomorrow', 'deadline' => today()->addDay()]);
        app(ConversationService::class)->start($client, ['project_id' => $project->id, 'recipient_id' => $freelancer->id, 'body' => 'Collaborating']);
        $milestone = Milestone::create(['project_id' => $project->id, 'title' => 'First milestone', 'acceptance_criteria' => 'Approved work', 'due_date' => today()]);
        Milestone::create(['project_id' => $project->id, 'title' => 'Already finished', 'acceptance_criteria' => 'Done', 'due_date' => today(), 'status' => 'completed']);
        Project::create(['client_id' => $client->id, 'title' => 'Distant', 'deadline' => today()->addDays(3)]);
        Project::create(['client_id' => $client->id, 'title' => 'Closed', 'deadline' => today(), 'status' => 'closed']);
        $this->artisan('notifications:send-deadlines')->assertSuccessful();
        $this->artisan('notifications:send-deadlines')->assertSuccessful();
        $this->assertSame(2, $client->notifications()->where('type', 'deadlines')->count());
        $this->assertSame(2, $freelancer->notifications()->where('type', 'deadlines')->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $milestone->update(['due_date' => today()->addDay()]);
        $this->artisan('notifications:send-deadlines')->assertSuccessful();
        $this->assertSame(3, $client->notifications()->where('type', 'deadlines')->count());
    }

    public function test_deadline_job_honors_preferences(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->notificationPreference()->create(['deadlines' => false]);
        Project::create(['client_id' => $client->id, 'title' => 'Muted deadline', 'deadline' => today()]);
        $this->artisan('notifications:send-deadlines')->assertSuccessful();
        $this->assertSame(0, $client->notifications()->count());
    }

    public function test_notification_titles_are_escaped(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'messages', '<script>alert("unsafe")</script>', '/messages');
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);
    }

    public function test_notification_service_rejects_external_links(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(NotificationService::class)->send(User::factory()->create(), 'messages', 'Unsafe destination', '//example.com');
    }

    public function test_polled_pagination_links_return_to_the_full_notification_page(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            app(NotificationService::class)->send($user, 'messages', 'Update '.$i, '/messages');
        }
        $this->actingAs($user)->get('/notifications/poll')->assertOk()
            ->assertSee(route('notifications.index').'?page=2', false)
            ->assertDontSee(route('notifications.poll').'?page=2', false);
    }
}
