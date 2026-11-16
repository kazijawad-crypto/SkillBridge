<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:200']])['q'] ?? '';
        $conversations = Conversation::forUser($request->user())
            ->with(['project', 'firstUser', 'secondUser'])
            ->when($search !== '', fn ($query) => $query->whereHas('messages', fn ($messages) => $messages
                ->where('user_id', $request->user()->id)->where('body', 'like', '%'.$search.'%')))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('messages.index', compact('conversations', 'search'));
    }

    public function create(Request $request): View
    {
        $request->validate([
            'context_type' => ['sometimes', 'in:project,task,proposal,review'],
            'context_id' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'integer'],
            'recipient_id' => ['nullable', 'integer'],
        ]);
        $projects = Project::with('client')->when($request->user()->isClient(), fn ($q) => $q->where('client_id', $request->user()->id))
            ->orderBy('title')->get();
        $recipients = User::where('id', '!=', $request->user()->id)
            ->when($request->user()->isClient(), fn ($q) => $q->whereIn('role', ['freelancer', 'admin']))
            ->when($request->user()->isFreelancer(), fn ($q) => $q->where('role', 'client'))
            ->orderBy('name')->get(['id', 'name', 'role']);

        return view('messages.create', compact('projects', 'recipients'));
    }

    public function store(StoreConversationRequest $request, ConversationService $service): RedirectResponse
    {
        $conversation = $service->start($request->user(), $request->validated());

        return redirect()->route('messages.show', $conversation)->with('success', 'Message sent.');
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:200']])['q'] ?? '';
        $messages = $conversation->messages()->with('user')
            ->when($search !== '', fn ($query) => $query->where('user_id', $request->user()->id)->where('body', 'like', '%'.$search.'%'))
            ->orderByDesc('id')->paginate(30)->withPath(route('messages.show', $conversation))->withQueryString();

        return view($request->routeIs('messages.poll') ? 'messages._thread' : 'messages.show', compact('conversation', 'messages', 'search'));
    }

    public function send(MessageRequest $request, Conversation $conversation, ConversationService $service): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $service->send($conversation, $request->user(), $request->validated('body'));

        return redirect()->route('messages.show', $conversation)->with('success', 'Message sent.');
    }

    public function update(MessageRequest $request, Conversation $conversation, Message $message): RedirectResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        $this->authorize('update', $message);
        $message->update(['body' => $request->validated('body'), 'edited_at' => now()]);

        return redirect()->route('messages.show', $conversation)->with('success', 'Message updated.');
    }

    public function destroy(Conversation $conversation, Message $message): RedirectResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        $this->authorize('delete', $message);
        $message->delete();

        return redirect()->route('messages.show', $conversation)->with('success', 'Message deleted.');
    }
}
