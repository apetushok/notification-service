<?php

namespace App\Http\Controllers;

use App\Actions\SendBatchNotificationAction;
use App\DTO\SendNotificationDTO;
use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\SendNotificationRequest;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="API для массовой рассылки уведомлений"
 * )
 */
class NotificationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/notifications/send",
     *     summary="Массовая отправка уведомлений",
     *     description="Отправка SMS или Email уведомлений группе получателей. Поддерживает идемпотентность.",
     *     operationId="sendNotification",
     *     tags={"Notifications"},
     *     security={},
     *
     *     @OA\Parameter(
     *         name="X-Idempotency-Key",
     *         in="header",
     *         description="Ключ идемпотентности для предотвращения дублирования",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"channel", "content", "recipients"},
     *             @OA\Property(property="channel", type="string", enum={"sms", "email"}, example="email", description="Канал отправки"),
     *             @OA\Property(property="priority", type="string", enum={"transactional", "high", "normal", "low"}, default="normal", example="high", description="Приоритет доставки"),
     *             @OA\Property(property="content", type="string", maxLength=5000, example="Your order #12345 has been shipped!", description="Текст сообщения"),
     *             @OA\Property(property="recipients", type="array", minItems=1, maxItems=10000,
     *                 @OA\Items(type="string", example="user@example.com"),
     *                 description="Массив получателей"
     *             ),
     *             @OA\Property(property="metadata", type="object", nullable=true, description="Дополнительные метаданные",
     *                 @OA\Property(property="template", type="string", example="order_shipped"),
     *                 @OA\Property(property="order_id", type="integer", example=12345)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Уведомление принято в обработку",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="batch_id", type="string", format="uuid", example="9a8b7c6d-5e4f-3a2b-1c0d-9e8f7a6b5c4d"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="message", type="string", example="Batch accepted and will be processed")
     *             ),
     *             @OA\Property(property="idempotent", type="boolean", example=false)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Повторный запрос с тем же ключом идемпотентности",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="batch_id", type="string", format="uuid"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="message", type="string")
     *             ),
     *             @OA\Property(property="idempotent", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Конфликт идемпотентности - запрос уже в обработке",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="idempotency_conflict"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="retry_after", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Ошибка валидации"),
     *     @OA\Response(response=429, description="Слишком много запросов")
     * )
     */
    public function send(SendNotificationRequest $request, SendBatchNotificationAction $sendBatch): JsonResponse
    {
        $dto = SendNotificationDTO::fromRequest($request);

        try {
            $result = $sendBatch->execute($dto);
        } catch (IdempotencyConflictException $e) {
            return response()->json([
                'error' => 'idempotency_conflict',
                'message' => $e->getMessage(),
                'retry_after' => 1,
            ], 409);
        }

        return response()->json([
            'data' => [
                'batch_id' => $result['batch_id'],
                'status' => 'pending',
                'message' => 'Batch accepted and will be processed',
            ],
            'idempotent' => $result['idempotent'],
        ], $result['idempotent'] ? 200 : 202);
    }
}
