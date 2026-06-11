<?php

namespace App\Http\Controllers;

use App\Services\Debezium\DebeziumHealthChecker;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function debezium(DebeziumHealthChecker $health): JsonResponse
    {
        return response()->json([
            'healthy' => $health->isHealthy(),
            'last_event' => \Illuminate\Support\Facades\Cache::get('kafka-connect:health:last_event'),
            'fallback_active' => !$health->isHealthy(),
        ]);
    }

    public function health(): \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
    {
        return response('OK');
    }
}
