<?php

namespace App\Http\Controllers;

use App\Models\InternalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $notification = $this->queryForUser($request)->latest('id')->first();

        return response()->json([
            'notification' => $notification ? $this->serialize($notification) : null,
        ]);
    }

    public function acknowledge(Request $request, InternalNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $level = $notification->data['level'] ?? 'leve';

        $notification->forceFill(['read_at' => now()])->save();

        return response()->json(['ok' => true, 'restricted' => $level !== 'leve']);
    }

    private function queryForUser(Request $request)
    {
        return InternalNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'moderation_warning')
            ->whereNull('read_at');
    }

    private function serialize(InternalNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'level' => $notification->data['level'] ?? 'leve',
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
