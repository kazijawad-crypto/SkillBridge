<div class="space-y-4">
    @forelse($messages->getCollection()->reverse() as $message)
        <article class="bg-white p-5 rounded-lg shadow border-l-4 {{ $message->user_id === auth()->id() ? 'border-indigo-500' : 'border-gray-300' }}">
            <div class="flex flex-wrap gap-2 justify-between text-sm">
                <span class="font-semibold text-gray-800">{{ $message->user->name }}{{ $message->user_id === auth()->id() ? ' (you)' : '' }}</span>
                <time class="text-gray-500" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y H:i') }}{{ $message->edited_at ? ' (edited)' : '' }}</time>
            </div>
            <p class="mt-3 text-gray-800 whitespace-pre-wrap break-words">{{ $message->body }}</p>
            @can('update', $message)
                <div class="mt-3 flex flex-wrap gap-4 items-start text-sm">
                    <details class="flex-1">
                        <summary class="text-indigo-600 cursor-pointer">Edit</summary>
                        <form method="POST" action="{{ route('messages.update', [$conversation, $message]) }}" class="mt-2 space-y-2">
                            @csrf @method('PATCH')
                            <label for="message-{{ $message->id }}" class="sr-only">Edit message</label>
                            <textarea id="message-{{ $message->id }}" name="body" required maxlength="5000" rows="3" class="w-full border-gray-300 rounded-md">{{ $message->body }}</textarea>
                            <x-primary-button>Save changes</x-primary-button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('messages.destroy', [$conversation, $message]) }}" onsubmit="return confirm('Delete this message?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 underline">Delete</button>
                    </form>
                </div>
            @endcan
        </article>
    @empty
        <p class="p-5 bg-white rounded-lg text-gray-500">{{ $search !== '' ? 'No matching messages you sent.' : 'No messages yet.' }}</p>
    @endforelse
    {{ $messages->links() }}
</div>
