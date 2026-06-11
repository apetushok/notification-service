<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 128)->unique();
            $table->string('endpoint', 255)->comment('API endpoint');
            $table->string('http_method', 10)->comment('HTTP метод');
            $table->jsonb('request_hash')->comment('Хеш тела запроса');
            $table->integer('response_code')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('status', 20)->default('processing')->comment('processing, completed, failed');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['key', 'status'], 'idx_key_status');
            $table->index(['expires_at'], 'idx_expires_at');
        });

        DB::statement('COMMENT ON TABLE idempotency_keys IS \'Ключи идемпотентности для защиты от дублирования запросов\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
