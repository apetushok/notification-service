<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Notification\NotificationOrchestrator;
use App\Services\Notification\Channels\ChannelManager;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\Providers\Email\StubSendGridProvider;
use App\Services\Notification\Providers\Email\StubMailgunProvider;
use App\Services\Notification\Providers\Email\StubAmazonSESProvider;
use App\Services\Notification\Providers\Sms\StubTwilioProvider;
use App\Services\Notification\Providers\Sms\StubVonageProvider;
use App\Services\Notification\Providers\Sms\StubTele2Provider;
use App\Services\Notification\CircuitBreaker;
use App\Services\Notification\RateLimiter;
use App\Services\Kafka\KafkaProducer;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Каналы
        $this->app->singleton(EmailChannel::class, function () {
            return new EmailChannel([
                app(StubSendGridProvider::class),
                app(StubMailgunProvider::class),
                app(StubAmazonSESProvider::class),
            ]);
        });

        $this->app->singleton(SmsChannel::class, function () {
            return new SmsChannel([
                app(StubTwilioProvider::class),
                app(StubVonageProvider::class),
                app(StubTele2Provider::class),
            ]);
        });

        // ChannelManager
        $this->app->singleton(ChannelManager::class, function ($app) {
            $manager = new ChannelManager();
            $manager->register('email', $app->make(EmailChannel::class));
            $manager->register('sms', $app->make(SmsChannel::class));
            return $manager;
        });

        // Инфраструктура
        $this->app->singleton(CircuitBreaker::class);
        $this->app->singleton(RateLimiter::class);
        $this->app->singleton(KafkaProducer::class);

        // Оркестратор
        $this->app->singleton(NotificationOrchestrator::class);
    }
}
