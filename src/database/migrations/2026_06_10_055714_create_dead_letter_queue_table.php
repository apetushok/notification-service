<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dead_letter_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->string('topic', 255)->comment('Kafka топик');
            $table->integer('partition')->nullable();
            $table->bigInteger('offset')->nullable();
            $table->text('payload')->comment('Оригинальное сообщение');
            $table->jsonb('headers')->nullable();
            $table->string('error_type', 100)->comment('Тип ошибки');
            $table->text('error_message');
            $table->jsonb('error_context')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(5);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, retrying, resolved, failed');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        DB::statement('COMMENT ON TABLE dead_letter_queue IS \'Очередь недоставленных сообщений для ручного разбора\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_letter_queue');
    }
};
