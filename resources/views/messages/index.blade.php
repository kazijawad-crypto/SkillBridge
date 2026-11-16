<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800">Messages</h2>
            <a href="{{ route('messages.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md">New conversation</a>
        </div>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-5">
        <form method="GET" class="flex gap-2">
            <label for="q" class="sr-only">Search your messages</label>
            <x-text-input id="q" name="q" :value="$search" placeholder="Search messages you sent" maxlength="200" class="flex-1 min-w-0" />
            <x-primary-button>Search</x-primary-button>
        </form>
        <x-input-error :messages="$errors->get('q')" />
        <div class="bg-white shadow rounded-lg divide-y">
            @forelse($conversations as $conversation)
                <a href="{{ route('messages.show', ['conversation' => $conversation, 'q' => $search]) }}" class="block p-5 hover:bg-gray-50">
                    <p class="font-semibold text-gray-800">{{ $conversation->otherUser(auth()->user())->name }}</p>
                    <p class="text-sm text-gray-600">{{ $conversation->project->title }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ ucfirst($conversation->context_type) }} #{{ $conversation->context_id }} &middot; {{ $conversation->updated_at->diffForHumans() }}</p>
                </a>
            @empty
                <p class="p-6 text-gray-500">{{ $search !== '' ? 'No conversations contain a matching message you sent.' : 'No conversations yet. Start one from a project or use New conversation.' }}</p>
            @endforelse
        </div>
        {{ $conversations->links() }}
    </div>
</x-app-layout>
