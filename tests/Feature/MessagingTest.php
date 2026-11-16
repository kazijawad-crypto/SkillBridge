<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $freelancer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'client']);
        $this->freelancer = User::factory()->create(['role' => 'freelancer']);
        $this->project = Project::create(['client_id' => $this->client->id, 'title' => 'Website project']);
    }

    private function conversation(string $body = 'First message'): Conversation
    {
        return app(ConversationService::class)->start($this->client, [
            'project_id' => $this->project->id, 'recipient_id' => $this->freelancer->id, 'body' => $body,
        ]);
    }

    public function test_guests_cannot_access_messaging(): void
    {
        $conversation = $this->conversation();
        foreach (['/messages', '/messages/create', "/messages/{$conversation->id}", "/messages/{$conversation->id}/poll"] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/messages', [])->assertRedirect('/login');
    }

    public function test_client_starts_a_project_conversation_and_recipient_is_notified(): void
    {
        $this->actingAs($this->client)->get('/messages/create')->assertOk()->assertSee('New conversation');
        $response = $this->post('/messages', [
            'project_id' => $this->project->id, 'recipient_id' => $this->freelancer->id,
            'body' => 'Can you build this?', 'user_id' => $this->freelancer->id,
        ]);
        $conversation = Conversation::sole();
        $response->assertRedirect(route('messages.show', $conversation));
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'user_id' => $this->client->id, 'body' => 'Can you build this?']);
        $this->assertSame(1, $this->freelancer->unreadNotifications()->count());
        $this->assertSame(0, $this->client->notifications()->count());
        $this->actingAs($this->freelancer)->get(route('messages.show', $conversation))->assertOk()->assertSee('Can you build this?');
    }

    public function test_starting_again_from_the_other_side_reuses_the_conversation(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->freelancer)->post('/messages', [
            'project_id' => $this->project->id, 'recipient_id' => $this->client->id, 'body' => 'Yes, I can.',
        ])->assertRedirect(route('messages.show', $conversation));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_admin_can_participate_but_cannot_read_another_conversation(): void
    {
        $private = $this->conversation();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('messages.show', $private))->assertNotFound();
        $this->post('/messages', [
            'project_id' => $this->project->id, 'recipient_id' => $this->client->id, 'body' => 'Project support',
        ])->assertRedirect();
        $this->get('/messages')->assertOk()->assertSee('Website project');
    }

    public function test_nonparticipants_cannot_read_poll_reply_edit_or_delete(): void
    {
        $conversation = $this->conversation('Private message');
        $message = $conversation->messages()->sole();
        $this->actingAs(User::factory()->create(['role' => 'freelancer']));
        $this->get(route('messages.show', $conversation))->assertNotFound();
        $this->get(route('messages.poll', $conversation))->assertNotFound();
        $this->post(route('messages.send', $conversation), ['body' => 'Intrusion'])->assertNotFound();
        $this->patch(route('messages.update', [$conversation, $message]), ['body' => 'Changed'])->assertNotFound();
        $this->delete(route('messages.destroy', [$conversation, $message]))->assertNotFound();
        $this->get('/messages')->assertOk()->assertDontSee('Website project');
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'body' => 'Private message']);
    }

    public function test_only_the_author_can_edit_and_delete_a_message(): void
    {
        $conversation = $this->conversation();
        $message = $conversation->messages()->sole();
        $this->actingAs($this->freelancer)->patch(route('messages.update', [$conversation, $message]), ['body' => 'Wrong author'])->assertForbidden();
        $this->delete(route('messages.destroy', [$conversation, $message]))->assertForbidden();
        $this->actingAs($this->client)->patch(route('messages.update', [$conversation, $message]), ['body' => 'Updated text', 'user_id' => $this->freelancer->id])->assertRedirect();
        $this->assertSame('Updated text', $message->fresh()->body);
        $this->assertSame($this->client->id, $message->fresh()->user_id);
        $this->assertNotNull($message->fresh()->edited_at);
        $this->delete(route('messages.destroy', [$conversation, $message]))->assertRedirect();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_message_from_a_different_conversation_cannot_be_modified_through_the_url(): void
    {
        $first = $this->conversation();
        $otherFreelancer = User::factory()->create(['role' => 'freelancer']);
        $second = app(ConversationService::class)->start($this->client, [
            'project_id' => $this->project->id, 'recipient_id' => $otherFreelancer->id, 'body' => 'Other conversation',
        ]);
        $message = $second->messages()->sole();
        $this->actingAs($this->client)->patch(route('messages.update', [$first, $message]), ['body' => 'Wrong thread'])->assertNotFound();
        $this->delete(route('messages.destroy', [$first, $message]))->assertNotFound();
    }

    public function test_search_returns_only_messages_sent_by_the_current_user(): void
    {
        $conversation = $this->conversation('Client confidential keyword');
        app(ConversationService::class)->send($conversation, $this->freelancer, 'Freelancer keyword reply');
        $this->actingAs($this->freelancer)->get(route('messages.show', ['conversation' => $conversation, 'q' => 'keyword']))
            ->assertOk()->assertSee('Freelancer keyword reply')->assertDontSee('Client confidential keyword');
        $this->get('/messages?q=confidential')->assertOk()->assertDontSee('Website project');
        $this->get('/messages?q=keyword')->assertOk()->assertSee('Website project');
    }

    public function test_messages_are_escaped_in_both_page_and_poll_response(): void
    {
        $conversation = $this->conversation('<script>alert("unsafe")</script>');
        $this->actingAs($this->freelancer);
        foreach (['messages.show', 'messages.poll'] as $route) {
            $this->get(route($route, $conversation))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);
        }
    }

    public function test_conversation_and_message_input_is_validated(): void
    {
        $this->actingAs($this->client)->postJson('/messages', [])->assertUnprocessable()->assertJsonValidationErrors(['project_id', 'recipient_id', 'body']);
        $this->postJson('/messages', ['project_id' => $this->project->id, 'recipient_id' => $this->client->id, 'body' => 'Self'])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
        $conversation = $this->conversation();
        foreach (['', '   ', str_repeat('a', 5001)] as $body) {
            $this->postJson(route('messages.send', $conversation), ['body' => $body])->assertUnprocessable()->assertJsonValidationErrors('body');
        }
        $this->getJson('/messages?q[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_client_cannot_start_a_conversation_about_someone_elses_project(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']))->post('/messages', [
            'project_id' => $this->project->id, 'recipient_id' => $this->freelancer->id, 'body' => 'Wrong project',
        ])->assertForbidden();
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_context_must_belong_to_the_project_and_unbuilt_contexts_are_rejected(): void
    {
        $this->actingAs($this->client)->post('/messages', [
            'project_id' => $this->project->id, 'recipient_id' => $this->freelancer->id,
            'body' => 'Wrong context', 'context_type' => 'project', 'context_id' => $this->project->id + 1,
        ])->assertNotFound();
        foreach (['task', 'proposal', 'review'] as $type) {
            $this->postJson('/messages', [
                'project_id' => $this->project->id, 'recipient_id' => $this->freelancer->id,
                'body' => 'Future context', 'context_type' => $type, 'context_id' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors('context_type');
        }
    }

    public function test_polled_pagination_links_return_to_the_full_conversation_page(): void
    {
        $conversation = $this->conversation();
        $conversation->messages()->createMany(array_fill(0, 30, ['user_id' => $this->client->id, 'body' => 'More messages']));
        $this->actingAs($this->client)->get(route('messages.poll', $conversation))
            ->assertOk()->assertSee(route('messages.show', $conversation).'?page=2', false)
            ->assertDontSee(route('messages.poll', $conversation).'?page=2', false);
        $this->get(route('messages.show', ['conversation' => $conversation, 'page' => 2]))
            ->assertOk()->assertSee('First message');
    }

    public function test_search_for_zero_remains_filtered_instead_of_enabling_polling(): void
    {
        $conversation = $this->conversation('Reference 0');
        app(ConversationService::class)->send($conversation, $this->freelancer, 'Other message');
        $this->actingAs($this->client)->get(route('messages.show', ['conversation' => $conversation, 'q' => '0']))
            ->assertOk()->assertSee('Reference 0')->assertDontSee('Other message')->assertDontSee('data-poll-url', false);
    }
}
