<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知一覧を表示する（ログインユーザー自身の通知のみ、新しい順）。
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications;

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にする。宛先本人のみ操作可（DatabaseNotificationPolicy）。
     */
    public function read(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました');
    }
}
