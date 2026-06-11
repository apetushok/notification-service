<?php

namespace App\Services\Kafka;

use Illuminate\Support\Facades\Log;
use RdKafka\Producer;
use RdKafka\Conf;

class KafkaProducer
{
    private Producer $producer;

    public function __construct()
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', config('kafka.brokers'));
        $conf->set('compression.codec', 'lz4');
        $conf->set('acks', 'all');
        $conf->set('message.send.max.retries', 3);
        $conf->set('retry.backoff.ms', 100);

        $this->producer = new Producer($conf);
    }

    public function send(string $priority, array $data): bool
    {
        $topic = config("notifications.priorities.{$priority}.topic");

        if (!$topic) {
            throw new \InvalidArgumentException("Unknown priority: {$priority}");
        }

        try {
            $kafkaTopic = $this->producer->newTopic($topic);

            $payload = json_encode($data['payload'] ?? $data);
            $key = $data['key'] ?? null;

            $kafkaTopic->produce(
                RD_KAFKA_PARTITION_UA,
                0,
                $payload,
                $key
            );

            $this->producer->poll(0);

            // Ждем подтверждения
            $result = $this->producer->flush(10000);

            if ($result === RD_KAFKA_RESP_ERR_NO_ERROR) {
                Log::info('Message published to Kafka', [
                    'topic' => $topic,
                    'key' => $key,
                ]);
                return true;
            }

            Log::error('Failed to publish message to Kafka', [
                'topic' => $topic,
                'error' => $result,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Kafka publish error', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function sendBatch(string $priority, array $messages): array
    {
        $results = ['success' => 0, 'failed' => 0];

        foreach ($messages as $data) {
            try {
                if ($this->send($priority, $data)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Batch publish error', [
                    'priority' => $priority,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
