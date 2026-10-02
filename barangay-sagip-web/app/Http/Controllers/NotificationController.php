<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feature 10: Alerts and Notifications (in-app list view).
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Auth::user()->notifications()->paginate(20);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function markRead(string $id): RedirectResponse
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();

        return back();
    }

    /**
     * Scoped to the signed-in user, so one account can never delete
     * another's notification by guessing its id.
     */
    public function destroy(string $id): RedirectResponse
    {
        Auth::user()->notifications()->where('id', $id)->delete();

        return back()->with('status', 'Notification deleted.');
    }

    public function markAllRead(): RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
