<?php

namespace App\Console\Commands\Kafka;

use Illuminate\Console\Command;
use App\Services\Notification\NotificationOrchestrator;
use App\Repositories\NotificationRepository;
use App\Services\Kafka\KafkaConsumer;
use Illuminate\Support\Facades\Log;

class ConsumeNotifications extends Command
{
    protected $signature = 'kafka:consume-notifications {priority}';
    protected $description = 'Consume notifications from Kafka by priority';

    public function handle(
        NotificationOrchestrator $orchestrator,
        NotificationRepository $repository,
    ): void {
        $priority = $this->argument('priority');
        $topic = config("notifications.priorities.{$priority}.topic");
        $groupId = "notification-{$priority}-consumer";

        $this->info("Starting consumer [{$groupId}] for topic: {$topic}");

        $consumer = new KafkaConsumer([$topic], $groupId);

        $consumer->consume(function (array $data, ?string $key, string $topic) use ($orchestrator, $repository, $priority) {
            $notificationId = $data['notification_id'] ?? null;

            if (!$notificationId) {
                Log::warning('Message without notification_id', ['data' => $data]);
                return;
            }

            $notification = $repository->find($notificationId);

            if (!$notification) {
                Log::warning('Notification not found', ['id' => $notificationId]);
                return;
            }

            if ($notification->status !== 'queued') {
                Log::info('Notification already processed', [
                    'id' => $notificationId,
                    'status' => $notification->status,
                ]);
                return;
            }

            Log::info('Processing notification', [
                'id' => $notificationId,
                'channel' => $notification->channel,
                'priority' => $priority,
                'topic' => $topic,
            ]);

            try {
                $orchestrator->process($notification);
            } catch (\Exception $e) {
                Log::error('Failed to process notification', [
                    'id' => $notificationId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
