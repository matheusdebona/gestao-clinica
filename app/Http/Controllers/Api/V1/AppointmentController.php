<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Appointments\IndexAppointmentRequest;
use App\Http\Requests\Api\V1\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\Appointments\SyncAppointmentConsumptionsRequest;
use App\Http\Requests\Api\V1\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Models\Appointment;
use App\Models\Treatment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * Clinic-wide calendar index.
     *
     * Query: from, to, status, professional_user_id, client_id, q.
     */
    public function index(IndexAppointmentRequest $request): AnonymousResourceCollection
    {
        $query = Appointment::query()
            ->with(['client', 'professionalUser', 'treatment'])
            ->orderBy('scheduled_at')
            ->orderBy('id');

        if ($request->filled('from')) {
            $query->where('scheduled_at', '>=', $this->boundDate($request->input('from'), startOfDay: true));
        }

        if ($request->filled('to')) {
            $query->where('scheduled_at', '<=', $this->boundDate($request->input('to'), startOfDay: false));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('professional_user_id')) {
            $query->where('professional_user_id', $request->integer('professional_user_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->whereHas('client', function ($builder) use ($term): void {
                $builder->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('whatsapp', 'like', $term);
                });
            });
        }

        $defaultPerPage = $request->filled('from') || $request->filled('to') ? 100 : 20;
        $perPage = min(max($request->integer('per_page', $defaultPerPage), 1), 100);

        return AppointmentResource::collection($query->paginate($perPage));
    }

    public function indexForTreatment(Treatment $treatment): AnonymousResourceCollection
    {
        $appointments = $treatment->appointments()
            ->with(['consumptions', 'client', 'professionalUser'])
            ->orderByDesc('id')
            ->paginate(20);

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request, Treatment $treatment): JsonResponse
    {
        $result = $this->appointments->schedule($treatment, $request->validated());

        return (new AppointmentResource($result['appointment']->load(['client', 'consumptions', 'professionalUser', 'treatment']), [
            'warnings' => $result['warnings'],
        ]))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource(
            $appointment->load([
                'consumptions.product',
                'consumptions.salePayment.paymentMethod',
                'client',
                'treatment',
                'professionalUser',
            ])
        );
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource(
            $this->appointments->update($appointment, $request->validated())
        );
    }

    public function start(Appointment $appointment): AppointmentResource
    {
        $result = $this->appointments->start($appointment);

        return new AppointmentResource($result['appointment'], [
            'suggested_consumptions' => $result['suggested_consumptions'],
            'stock_warnings' => $result['stock_warnings'],
        ]);
    }

    public function syncConsumptions(
        SyncAppointmentConsumptionsRequest $request,
        Appointment $appointment,
    ): AppointmentResource {
        return new AppointmentResource(
            $this->appointments->syncConsumptions(
                $appointment,
                $request->validated('consumptions'),
            )
        );
    }

    public function complete(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource(
            $this->appointments->complete($appointment, request()->user())
        );
    }

    public function cancel(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($this->appointments->cancel($appointment));
    }

    private function boundDate(mixed $value, bool $startOfDay): Carbon
    {
        $parsed = Carbon::parse($value);
        $raw = is_string($value) ? $value : $parsed->toIso8601String();
        $hasTime = str_contains($raw, 'T') || preg_match('/\d{2}:\d{2}/', $raw) === 1;

        if ($hasTime) {
            return $parsed;
        }

        return $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();
    }
}
