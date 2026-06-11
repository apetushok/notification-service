<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_number', 64)->unique()->comment('Человекочитаемый номер пакета: BATCH-20240101-XXXX');
            $table->string('channel', 20)->comment('Канал отправки: sms, email, push');
            $table->string('priority', 20)->default('normal')->comment('Приоритет: transactional, high, normal, low');
            $table->text('content')->comment('Текст сообщения');
            $table->jsonb('recipients')->comment('Массив получателей');
            $table->jsonb('metadata')->nullable()->comment('Дополнительные метаданные (шаблон, отправитель и т.д.)');
            $table->string('status', 30)->default('pending')->comment('pending, processed, failed');
            $table->integer('attempt_count')->default(0);
            $table->text('error_message')->nullable();
            $table->string('created_by', 100)->nullable()->comment('Инициатор рассылки (сервис/пользователь)');
            $table->timestamp('scheduled_at')->nullable()->comment('Запланированное время отправки');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['status', 'created_at']);
            $table->index('updated_at');
        });

        DB::statement('COMMENT ON TABLE notification_batches IS \'Пакеты массовых рассылок уведомлений\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
