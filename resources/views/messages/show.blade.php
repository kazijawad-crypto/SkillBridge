<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">Conversation with {{ $conversation->otherUser(auth()->user())->name }}</h2>
        <a href="{{ route('projects.show', $conversation->project) }}" class="text-sm text-indigo-600 underline">{{ $conversation->project->title }}</a>
        <p class="text-xs text-gray-500 mt-1">{{ ucfirst($conversation->context_type) }} #{{ $conversation->context_id }}</p>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-5">
        <a href="{{ route('messages.index') }}" class="text-sm text-indigo-600 underline">All conversations</a>
        @if(session('success'))<p role="status" class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</p>@endif
        <form method="GET" class="flex gap-2">
            <label for="q" class="sr-only">Search messages you sent</label>
            <x-text-input id="q" name="q" :value="$search" placeholder="Search messages you sent" maxlength="200" class="flex-1 min-w-0" />
            <x-primary-button>Search</x-primary-button>
            @if($search !== '')<a href="{{ route('messages.show', $conversation) }}" class="self-center text-sm underline">Clear</a>@endif
        </form>
        <x-input-error :messages="$errors->get('q')" />
        <p class="text-xs text-gray-500">Updates every 10 seconds while this page is active. Search shows only your own messages.</p>
        <div @if($search === '' && !request('page')) data-poll-url="{{ route('messages.poll', $conversation) }}" @endif>
            @include('messages._thread')
        </div>
        <form method="POST" action="{{ route('messages.send', $conversation) }}" class="bg-white p-5 rounded-lg shadow space-y-3">
            @csrf
            <x-input-label for="body" value="Reply" />
            <textarea id="body" name="body" required maxlength="5000" rows="3" class="w-full border-gray-300 rounded-md">{{ old('body') }}</textarea>
            <x-input-error :messages="$errors->get('body')" />
            <x-primary-button>Send reply</x-primary-button>
        </form>
    </div>
</x-app-layout>
