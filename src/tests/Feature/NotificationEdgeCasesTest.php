<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Services\Kafka\KafkaProducer;
use App\Models\Notification;
use Mockery;

class NotificationEdgeCasesTest extends TestCase
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

    public function test_404_for_nonexistent_notification(): void
    {
        $r = $this->getJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000');
        $r->assertStatus(404);
    }

    public function test_default_priority_is_normal(): void
    {
        $this->postJson('/api/v1/notifications/send', [
            'channel' => 'email',
            'content' => 'Test',
            'recipients' => ['test@test.com'],
        ])->assertStatus(202);

        $this->assertDatabaseHas('notification_batches', ['priority' => 'normal']);
    }

    public function test_sms_channel(): void
    {
        $this->postJson('/api/v1/notifications/send', [
            'channel' => 'sms',
            'content' => 'SMS test',
            'recipients' => ['+1234567890'],
        ])->assertStatus(202);

        $this->assertDatabaseHas('notification_batches', ['channel' => 'sms']);
    }

    public function test_notification_statuses(): void
    {
        $n = Notification::create([
            'channel' => 'email',
            'priority' => 'high',
            'recipient' => 'status@test.com',
            'content' => 'Status check',
            'status' => 'queued',
            'max_attempts' => 3,
            'queued_at' => now(),
        ]);

        $this->getJson("/api/v1/notifications/{$n->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'queued');
    }
}
