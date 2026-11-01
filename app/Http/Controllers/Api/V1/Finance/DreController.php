<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\TaxSetting;
use App\Services\DreService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DreController extends Controller
{
    public function __invoke(Request $request, DreService $dre): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month'])->startOfMonth();

        return response()->json([
            'data' => [
                ...$dre->compare((int) CurrentClinic::id(), $month),
                'checks' => [
                    'tax_configured' => TaxSetting::query()->exists(),
                ],
            ],
        ]);
    }
}
