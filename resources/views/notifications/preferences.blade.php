<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Notification preferences</h2></x-slot>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-5">
        @if(session('success'))<p role="status" class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</p>@endif
        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf @method('PATCH')
            <p class="text-sm text-gray-600">Choose which updates appear in your notification center. Turning off message alerts does not stop messages from arriving.</p>
            @foreach($types as $type => $label)
                <div>
                    <input type="hidden" name="{{ $type }}" value="0">
                    <label class="flex gap-3 items-center">
                        <input type="checkbox" name="{{ $type }}" value="1" @checked(old($type, $preferences->$type ?? true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>{{ $label }}</span>
                    </label>
                    <x-input-error :messages="$errors->get($type)" />
                </div>
            @endforeach
            <div class="flex items-center gap-4">
                <x-primary-button>Save preferences</x-primary-button>
                <a href="{{ route('notifications.index') }}" class="text-sm text-gray-600 underline">Back to notifications</a>
            </div>
        </form>
    </div>
</x-app-layout>
