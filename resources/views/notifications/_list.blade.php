<div class="bg-white shadow rounded-lg divide-y">
    @forelse($notifications as $notification)
        <article class="p-5 {{ $notification->read_at ? '' : 'bg-indigo-50' }}">
            <div class="flex flex-wrap justify-between gap-3">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">{{ \App\Models\NotificationPreference::TYPES[$notification->type] ?? 'Activity' }}{{ $notification->read_at ? '' : ' / Unread' }}</p>
                    <a href="{{ $notification->data['url'] }}" class="block mt-1 font-medium text-indigo-700 underline">{{ $notification->data['title'] }}</a>
                    <time class="block mt-1 text-xs text-gray-500" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                </div>
                @unless($notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf @method('PATCH')
                        <button class="text-sm text-indigo-600 underline">Mark as read</button>
                    </form>
                @endunless
            </div>
        </article>
    @empty
        <p class="p-6 text-gray-500">{{ request('filter') === 'unread' ? 'You are all caught up. No unread notifications.' : 'No notifications yet.' }}</p>
    @endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
