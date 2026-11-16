<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationPreferenceRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['filter' => ['nullable', 'in:all,unread']]);
        $notifications = $request->user()->notifications()
            ->when($request->query('filter') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withPath(route('notifications.index'))->withQueryString();

        return view($request->routeIs('notifications.poll') ? 'notifications._list' : 'notifications.index', compact('notifications'));
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function preferences(Request $request): View
    {
        $preferences = $request->user()->notificationPreference ?? new NotificationPreference;
        $types = NotificationPreference::TYPES;

        return view('notifications.preferences', compact('preferences', 'types'));
    }

    public function updatePreferences(NotificationPreferenceRequest $request): RedirectResponse
    {
        $request->user()->notificationPreference()->updateOrCreate([], $request->validated());

        return redirect()->route('notifications.preferences')->with('success', 'Notification preferences saved.');
    }
}
