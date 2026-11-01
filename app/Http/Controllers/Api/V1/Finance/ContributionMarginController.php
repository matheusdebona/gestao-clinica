<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Services\ContributionMarginService;
use App\Support\ClinicMonth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContributionMarginController extends Controller
{
    public function __invoke(Request $request, ContributionMarginService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = ClinicMonth::parseDate($data['from']);
        $to = ClinicMonth::parseDate($data['to']);
        if ($from->diffInDays($to) > 400) {
            throw ValidationException::withMessages(['to' => ['Período máximo de 400 dias.']]);
        }

        return response()->json(['data' => $service->summarize($from, $to)]);
    }
}
