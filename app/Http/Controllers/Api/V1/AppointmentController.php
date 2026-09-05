<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\Appointments\SyncAppointmentConsumptionsRequest;
use App\Http\Requests\Api\V1\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Models\Appointment;
use App\Models\Treatment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function indexForTreatment(Treatment $treatment): AnonymousResourceCollection
    {
        $appointments = $treatment->appointments()
            ->with(['consumptions', 'client'])
            ->orderByDesc('id')
            ->paginate(20);

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request, Treatment $treatment): JsonResponse
    {
        $appointment = $this->appointments->schedule($treatment, $request->validated());

        return (new AppointmentResource($appointment->load(['client', 'consumptions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource(
            $appointment->load(['consumptions.product', 'client', 'treatment', 'professionalUser'])
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
}
