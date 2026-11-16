<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">New conversation</h2></x-slot>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <form method="POST" action="{{ route('messages.store') }}" class="bg-white p-6 shadow rounded-lg space-y-5"
              x-data="{ projectId: @js((string) old('project_id', request('project_id', ''))), recipientId: @js((string) old('recipient_id', request('recipient_id', ''))), clients: @js($projects->pluck('client_id', 'id')) }">
            @csrf
            <input type="hidden" name="context_type" value="{{ old('context_type', request('context_type', 'project')) }}">
            @if(request('context_id'))<input type="hidden" name="context_id" value="{{ request('context_id') }}">@endif
            <div>
                <x-input-label for="project_id" value="Project" />
                <select id="project_id" name="project_id" x-model="projectId" required class="mt-1 w-full border-gray-300 rounded-md"
                        @change="if (clients[projectId] != {{ auth()->id() }}) recipientId = String(clients[projectId] || '')">
                    <option value="">Choose a project</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->title }} &mdash; {{ $project->client->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('project_id')" />
            </div>
            <div>
                <x-input-label for="recipient_id" value="Recipient" />
                <select id="recipient_id" name="recipient_id" x-model="recipientId" required class="mt-1 w-full border-gray-300 rounded-md">
                    <option value="">Choose a recipient</option>
                    @foreach($recipients as $recipient)
                        <option value="{{ $recipient->id }}"
                            @if(auth()->user()->isFreelancer()) :disabled="clients[projectId] != {{ $recipient->id }}" @endif>
                            {{ $recipient->name }} ({{ $recipient->role }})
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 mt-1">Freelancers contact the project's client. Clients contact freelancers or admins about their own projects.</p>
                <x-input-error :messages="$errors->get('recipient_id')" />
                <x-input-error :messages="$errors->get('context_type')" />
                <x-input-error :messages="$errors->get('context_id')" />
            </div>
            <div>
                <x-input-label for="body" value="Message" />
                <textarea id="body" name="body" required maxlength="5000" rows="5" class="mt-1 w-full border-gray-300 rounded-md">{{ old('body') }}</textarea>
                <x-input-error :messages="$errors->get('body')" />
            </div>
            <div class="flex gap-4 items-center">
                <x-primary-button>Send message</x-primary-button>
                <a href="{{ route('messages.index') }}" class="text-sm text-gray-600 underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
