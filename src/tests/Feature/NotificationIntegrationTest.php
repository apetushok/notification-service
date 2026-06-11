<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Services\Kafka\KafkaProducer;
use App\Services\IdempotencyService;
use App\Models\Notification;
use Mockery;

class NotificationIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $mockKafka = Mockery::mock(KafkaProducer::class);
        $mockKafka->shouldReceive('send')->andReturn(true);
        $mockKafka->shouldReceive('sendBatch')->andReturn(['success' => 2, 'failed' => 0]);
        $this->app->instance(KafkaProducer::class, $mockKafka);
    }

    public function test_send_creates_batch(): void
    {
        $r = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email',
            'priority' => 'high',
            'content' => 'Test',
            'recipients' => ['a@b.com', 'c@d.com'],
        ]);

        $r->assertStatus(202);
        $this->assertDatabaseHas('notification_batches', ['channel' => 'email']);
    }

    public function test_idempotency_returns_200(): void
    {
        $key = 'test-' . time();

        $r1 = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email', 'content' => 'T', 'recipients' => ['t@t.com'],
        ], ['X-Idempotency-Key' => $key]);

        $r1->assertStatus(202);

        $r2 = $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email', 'content' => 'T', 'recipients' => ['t@t.com'],
        ], ['X-Idempotency-Key' => $key]);

        $r2->assertStatus(200)->assertJson(['idempotent' => true]);
    }

    public function test_notification_status(): void
    {
        $n = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+123',
            'content' => 'Status test',
            'status' => 'sent',
            'max_attempts' => 3,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        $r = $this->getJson("/api/v1/notifications/{$n->id}");
        $r->assertStatus(200)->assertJson(['data' => ['status' => 'sent']]);
    }

    public function test_recipient_history(): void
    {
        Notification::create([
            'channel' => 'email',
            'priority' => 'normal',
            'recipient' => 'history@test.com',
            'content' => 'History test',
            'status' => 'delivered',
            'max_attempts' => 3,
            'queued_at' => now(),
            'delivered_at' => now(),
        ]);

        $r = $this->getJson('/api/v1/notifications/recipient/history@test.com');
        $r->assertStatus(200)->assertJsonCount(1, 'data');
    }
}
