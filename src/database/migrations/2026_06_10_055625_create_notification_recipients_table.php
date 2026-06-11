<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('recipient', 255)->comment('Нормализованный адрес (телефон/email)');
            $table->uuid('user_id')->nullable()->comment('Связь с пользователем системы');
            $table->string('channel', 20)->comment('Предпочитаемый канал');
            $table->jsonb('channels')->nullable()->comment('Доступные каналы и адреса');

            $table->string('status', 20)->default('active')->comment('Статус: active, unsubscribed, blocked, invalid');
            $table->string('status_reason', 255)->nullable()->comment('Причина статуса');

            $table->boolean('is_valid')->default(true)->comment('Валидность адреса');
            $table->timestamp('last_validated_at')->nullable();
            $table->string('validation_method', 50)->nullable()->comment('Метод валидации');

            $table->integer('total_sent')->default(0);
            $table->integer('total_delivered')->default(0);
            $table->integer('total_failed')->default(0);
            $table->timestamp('last_notification_at')->nullable();

            $table->jsonb('preferences')->nullable()->comment('Предпочтения: часовой пояс, тихое время и т.д.');
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['recipient', 'channel'], 'uq_recipient_channel');
        });

        DB::statement('COMMENT ON TABLE notification_recipients IS \'Справочник получателей уведомлений\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
