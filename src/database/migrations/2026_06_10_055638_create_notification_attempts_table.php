<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->integer('attempt_number');
            $table->string('provider', 50)->nullable();
            $table->string('status', 30)->comment('Статус попытки: success, failed, timeout, rejected');
            $table->smallInteger('http_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->jsonb('request_payload')->nullable()->comment('Отправленные данные');
            $table->jsonb('response_payload')->nullable()->comment('Ответ провайдера');
            $table->decimal('response_time_ms', 10, 2)->nullable()->comment('Время ответа в миллисекундах');
            $table->string('provider_message_id', 255)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->onDelete('cascade');

            $table->unique(['notification_id', 'attempt_number'], 'uq_notification_attempt');
        });

        DB::statement('COMMENT ON TABLE notification_attempts IS \'История всех попыток отправки уведомлений\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
