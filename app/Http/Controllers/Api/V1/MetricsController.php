<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Metrics\CommercialMetricsRequest;
use App\Services\CommercialMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __construct(private readonly CommercialMetricsService $commercialMetrics) {}

    public function commercial(CommercialMetricsRequest $request): JsonResponse
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $request->string('from')->toString())->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $request->string('to')->toString())->startOfDay();
        $granularity = $request->filled('granularity')
            ? $request->string('granularity')->toString()
            : null;

        return response()->json([
            'data' => $this->commercialMetrics->summarize($from, $to, $granularity),
        ]);
    }
}
