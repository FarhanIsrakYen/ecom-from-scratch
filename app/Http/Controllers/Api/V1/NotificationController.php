<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'unread' => ['sometimes', 'boolean'],
        ]);

        $notifications = $request->user()
            ->notifications()
            ->when((bool) ($filters['unread'] ?? false), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20);

        return $this->success(NotificationResource::collection($notifications->items()), 'Notifications retrieved.', 200, [
            'current_page' => $notifications->currentPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return $this->success(new NotificationResource($record->refresh()), 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(null, 'Notifications marked as read.');
    }
}
