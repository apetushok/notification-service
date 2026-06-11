<?php

namespace App\Services\Kafka;

use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use Illuminate\Support\Facades\Log;

class KafkaConsumer
{
    private RdKafkaConsumer $consumer;

    public function __construct(array $topics, string $groupId)
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', config('kafka.brokers'));
        $conf->set('group.id', $groupId);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.commit.interval.ms', '5000');
        $conf->set('session.timeout.ms', '30000');
        $conf->set('max.poll.interval.ms', '300000');

        $this->consumer = new RdKafkaConsumer($conf);
        $this->consumer->subscribe($topics);
    }

    public function consume(callable $handler, int $timeoutMs = 10000): void
    {
        while (true) {
            $message = $this->consumer->consume($timeoutMs);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    try {
                        $payload = json_decode($message->payload, true);
                        $handler($payload, $message->key, $message->topic_name);
                    } catch (\Exception $e) {
                        Log::error('Consumer handler error', [
                            'topic' => $message->topic_name,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    // Конец партиции, ждем новых сообщений
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // Таймаут ожидания
                    break;

                default:
                    Log::error('Kafka consumer error', [
                        'error' => $message->errstr(),
                        'code' => $message->err,
                    ]);
                    break;
            }
        }
    }
}
