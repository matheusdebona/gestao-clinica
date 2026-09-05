<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Metrics\AcquisitionMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\CommercialMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\MarginMetricsRequest;
use App\Services\AcquisitionMetricsService;
use App\Services\CommercialMetricsService;
use App\Services\MarginMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __construct(
        private readonly CommercialMetricsService $commercialMetrics,
        private readonly AcquisitionMetricsService $acquisitionMetrics,
        private readonly MarginMetricsService $marginMetrics,
    ) {}

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

    public function acquisition(AcquisitionMetricsRequest $request): JsonResponse
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $request->string('from')->toString())->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $request->string('to')->toString())->startOfDay();
        $groupBy = $request->filled('group_by')
            ? $request->string('group_by')->toString()
            : AcquisitionMetricsService::GROUP_BY_ORIGIN;

        return response()->json([
            'data' => $this->acquisitionMetrics->summarize($from, $to, $groupBy),
        ]);
    }

    public function margin(MarginMetricsRequest $request): JsonResponse
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $request->string('from')->toString())->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $request->string('to')->toString())->startOfDay();
        $mode = $request->filled('mode')
            ? $request->string('mode')->toString()
            : MarginMetricsService::MODE_PERIOD;

        return response()->json([
            'data' => $this->marginMetrics->summarize($from, $to, $mode),
        ]);
    }
}
