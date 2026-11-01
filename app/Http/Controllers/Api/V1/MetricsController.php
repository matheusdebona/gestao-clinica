<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Metrics\AcquisitionMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\CommercialMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\InventoryMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\LeadMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\MarginMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\OperationsMetricsRequest;
use App\Http\Requests\Api\V1\Metrics\SalesFunnelMetricsRequest;
use App\Services\AcquisitionMetricsService;
use App\Services\CommercialMetricsService;
use App\Services\InventoryMetricsService;
use App\Services\LeadMetricsService;
use App\Services\MarginMetricsService;
use App\Services\OperationsMetricsService;
use App\Services\SalesFunnelMetricsService;
use App\Support\ClinicMonth;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __construct(
        private readonly CommercialMetricsService $commercialMetrics,
        private readonly AcquisitionMetricsService $acquisitionMetrics,
        private readonly MarginMetricsService $marginMetrics,
        private readonly InventoryMetricsService $inventoryMetrics,
        private readonly OperationsMetricsService $operationsMetrics,
        private readonly LeadMetricsService $leadMetrics,
        private readonly SalesFunnelMetricsService $salesFunnel,
    ) {}

    public function commercial(CommercialMetricsRequest $request): JsonResponse
    {
        $from = ClinicMonth::parseDate($request->string('from')->toString());
        $to = ClinicMonth::parseDate($request->string('to')->toString());
        $granularity = $request->filled('granularity')
            ? $request->string('granularity')->toString()
            : null;

        return response()->json([
            'data' => $this->commercialMetrics->summarize($from, $to, $granularity),
        ]);
    }

    public function acquisition(AcquisitionMetricsRequest $request): JsonResponse
    {
        $from = ClinicMonth::parseDate($request->string('from')->toString());
        $to = ClinicMonth::parseDate($request->string('to')->toString());
        $groupBy = $request->filled('group_by')
            ? $request->string('group_by')->toString()
            : AcquisitionMetricsService::GROUP_BY_ORIGIN;

        return response()->json([
            'data' => $this->acquisitionMetrics->summarize($from, $to, $groupBy),
        ]);
    }

    public function margin(MarginMetricsRequest $request): JsonResponse
    {
        $from = ClinicMonth::parseDate($request->string('from')->toString());
        $to = ClinicMonth::parseDate($request->string('to')->toString());
        $mode = $request->filled('mode')
            ? $request->string('mode')->toString()
            : MarginMetricsService::MODE_PERIOD;

        return response()->json([
            'data' => $this->marginMetrics->summarize($from, $to, $mode),
        ]);
    }

    public function inventory(InventoryMetricsRequest $request): JsonResponse
    {
        $to = $request->filled('to')
            ? ClinicMonth::parseDate($request->string('to')->toString())
            : ClinicMonth::parseDate(ClinicMonth::todayDate()); // hoje em São Paulo
        $from = $request->filled('from')
            ? ClinicMonth::parseDate($request->string('from')->toString())
            : $to->subDays(29);

        return response()->json([
            'data' => $this->inventoryMetrics->summarize($from, $to),
        ]);
    }

    public function operations(OperationsMetricsRequest $request): JsonResponse
    {
        $from = ClinicMonth::parseDate($request->string('from')->toString());
        $to = ClinicMonth::parseDate($request->string('to')->toString());

        return response()->json([
            'data' => $this->operationsMetrics->summarize($from, $to),
        ]);
    }

    public function leads(LeadMetricsRequest $request): JsonResponse
    {
        $from = ClinicMonth::parseDate($request->string('from')->toString());
        $to = ClinicMonth::parseDate($request->string('to')->toString());

        return response()->json([
            'data' => $this->leadMetrics->summarize($from, $to),
        ]);
    }

    /**
     * End-to-end funnel: lead → abordado → respondeu → agendou → compareceu → orçamento → venda (+ receita).
     * Optional filters: client_origin_id, user_id (who registered the lead).
     */
    public function salesFunnel(SalesFunnelMetricsRequest $request): JsonResponse
    {
        $from = ClinicMonth::parseDate($request->string('from')->toString());
        $to = ClinicMonth::parseDate($request->string('to')->toString());

        return response()->json([
            'data' => $this->salesFunnel->summarize($from, $to, [
                'origin_id' => $request->filled('client_origin_id') ? $request->integer('client_origin_id') : null,
                'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
            ]),
        ]);
    }
}
