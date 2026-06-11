<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Создаем партиционирование по дате создания только для больших объемов > 5M/месяц

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable()->comment('Связь с пакетом рассылки');
            $table->string('channel', 20)->comment('Канал: sms, email, push');
            $table->string('priority', 20)->default('normal')->comment('Приоритет: transactional, high, normal, low');
            $table->string('recipient', 255)->comment('Адрес получателя (телефон/email)');
            $table->uuid('recipient_id')->nullable()->comment('ID получателя в системе');
            $table->text('content')->comment('Содержание уведомления');
            $table->jsonb('content_variables')->nullable()->comment('Переменные для шаблонизации');
            $table->jsonb('metadata')->nullable()->comment('Метаданные уведомления');

            $table->string('status', 30)->default('queued')->comment('Статус: queued, sending, sent, delivered, failed, discarded');
            $table->string('status_reason', 500)->nullable()->comment('Причина текущего статуса');
            $table->jsonb('status_history')->nullable()->comment('История изменения статусов');

            $table->string('provider', 50)->nullable()->comment('Провайдер отправки (twilio, sendgrid и т.д.)');
            $table->string('provider_message_id', 255)->nullable()->comment('ID сообщения у провайдера');
            $table->jsonb('provider_response')->nullable()->comment('Полный ответ провайдера');

            $table->string('idempotency_key', 128)->nullable()->unique()->comment('Ключ идемпотентности');

            $table->integer('attempt_count')->default(0)->comment('Количество попыток отправки');
            $table->integer('max_attempts')->default(3)->comment('Максимальное количество попыток');

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->comment('Время следующей попытки');

            $table->timestamps();

            $table->foreign('batch_id')
                ->references('id')
                ->on('notification_batches')
                ->onDelete('set null');
        });

        DB::statement('COMMENT ON TABLE notifications IS \'Уведомления с полной историей статусов\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
