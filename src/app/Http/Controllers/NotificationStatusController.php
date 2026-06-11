<?php

namespace App\Http\Controllers;

use App\Repositories\NotificationRepository;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Notification Status",
 *     description="Получение статусов уведомлений"
 * )
 */
class NotificationStatusController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/recipient/{recipient}",
     *     summary="Получить уведомления получателя",
     *     description="Возвращает историю уведомлений для конкретного получателя",
     *     operationId="getRecipientNotifications",
     *     tags={"Notification Status"},
     *
     *     @OA\Parameter(
     *         name="recipient",
     *         in="path",
     *         required=true,
     *         description="Email или телефон получателя",
     *         @OA\Schema(type="string", example="user@example.com")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Список уведомлений",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="channel", type="string", enum={"sms", "email"}),
     *                     @OA\Property(property="status", type="string", enum={"queued", "sending", "sent", "delivered", "failed", "discarded"}),
     *                     @OA\Property(property="content", type="string"),
     *                     @OA\Property(property="attempts", type="integer"),
     *                     @OA\Property(property="status_history", type="array", nullable=true, @OA\Items(type="object")),
     *                     @OA\Property(property="queued_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="failed_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="status_reason", type="string", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function byRecipient(string $recipient): JsonResponse
    {
        $notifications = $this->notifications->getByRecipient($recipient);

        return response()->json([
            'data' => $notifications->map(fn($notification) => [
                'id' => $notification->id,
                'channel' => $notification->channel,
                'status' => $notification->status,
                'content' => $notification->content,
                'attempts' => $notification->attempt_count,
                'status_history' => $notification->status_history,
                'queued_at' => $notification->queued_at?->toIso8601String(),
                'sent_at' => $notification->sent_at?->toIso8601String(),
                'delivered_at' => $notification->delivered_at?->toIso8601String(),
                'failed_at' => $notification->failed_at?->toIso8601String(),
                'status_reason' => $notification->status_reason,
            ]),
            'meta' => [
                'total' => $notifications->total(),
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/{id}",
     *     summary="Детальная информация об уведомлении",
     *     operationId="getNotification",
     *     tags={"Notification Status"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(response=200, description="Детали уведомления"),
     *     @OA\Response(response=404, description="Уведомление не найдено")
     * )
     */

    public function show(string $id): JsonResponse
    {
        $notification = $this->notifications->find($id);

        if (!$notification) {
            return response()->json([
                'error' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'batch_id' => $notification->batch_id,
                'channel' => $notification->channel,
                'priority' => $notification->priority,
                'recipient' => $notification->recipient,
                'status' => $notification->status,
                'status_history' => $notification->status_history,
                'attempts' => $notification->attempt_count,
                'max_attempts' => $notification->max_attempts,
                'provider' => $notification->provider,
                'provider_message_id' => $notification->provider_message_id,
                'timeline' => [
                    'queued' => $notification->queued_at?->toIso8601String(),
                    'sending' => $notification->sending_at?->toIso8601String(),
                    'sent' => $notification->sent_at?->toIso8601String(),
                    'delivered' => $notification->delivered_at?->toIso8601String(),
                    'failed' => $notification->failed_at?->toIso8601String(),
                ],
                'next_attempt' => $notification->next_attempt_at?->toIso8601String(),
            ],
        ]);
    }
}
