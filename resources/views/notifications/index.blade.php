<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800">Notifications</h2>
            <a href="{{ route('notifications.preferences') }}" class="text-sm text-indigo-600 underline">Notification preferences</a>
        </div>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-5">
        @if(session('success'))<p role="status" class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</p>@endif
        <div class="flex flex-wrap justify-between gap-3 items-center">
            <div class="flex gap-4 text-sm">
                <a href="{{ route('notifications.index') }}" class="text-indigo-600 underline">All notifications</a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="text-indigo-600 underline">Unread only</a>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf @method('PATCH')
                <x-secondary-button type="submit">Mark all as read</x-secondary-button>
            </form>
        </div>
        <p class="text-xs text-gray-500">Updates every 10 seconds while this page is active.</p>
        <div @if(!request('page')) data-poll-url="{{ route('notifications.poll', ['filter' => request('filter', 'all')]) }}" @endif>
            @include('notifications._list')
        </div>
    </div>
</x-app-layout>
