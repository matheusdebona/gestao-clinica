<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\IndexNotificationRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * @var array<string, list<string>>
     */
    private const CATEGORY_TYPES = [
        'stock' => ['low_stock', 'projected_low_stock'],
        'agenda' => ['appointment_stock_warning'],
    ];

    public function index(IndexNotificationRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()
            ->notifications()
            ->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $category = $request->validated('category');
        if (is_string($category) && isset(self::CATEGORY_TYPES[$category])) {
            $query->whereIn('data->type', self::CATEGORY_TYPES[$category]);
        }

        return NotificationResource::collection($query->paginate(20));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): NotificationResource
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return new NotificationResource($notification->fresh());
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
