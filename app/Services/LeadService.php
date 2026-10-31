<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Support\EnsureDefaultLeadStatuses;
use App\Support\LeadPhone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /**
     * @param  array{name: string, phone: string, client_origin_id: int, lead_status_id?: int|null, notes?: string|null}  $data
     */
    public function create(array $data, User $user): Lead
    {
        $this->assertPhoneAvailable((int) $user->clinic_id, (string) $data['phone'], null);

        try {
            return $this->createLead($data, $user);
        } catch (UniqueConstraintViolationException) {
            // Concurrent insert of the same phone: re-run the check to return the structured error.
            $this->assertPhoneAvailable((int) $user->clinic_id, (string) $data['phone'], null);
            throw ValidationException::withMessages(['phone' => ['Já existe um lead com este telefone.']]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createLead(array $data, User $user): Lead
    {
        return DB::transaction(function () use ($data, $user): Lead {
            $statusId = $data['lead_status_id'] ?? null;
            if ($statusId === null) {
                $statusId = EnsureDefaultLeadStatuses::defaultFor((int) $user->clinic_id)->id;
            }

            $lead = Lead::query()->create([
                'clinic_id' => $user->clinic_id,
                'name' => trim($data['name']),
                'phone' => $data['phone'],
                'client_origin_id' => $data['client_origin_id'],
                'lead_status_id' => $statusId,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            $lead->activities()->create([
                'user_id' => $user->id,
                'type' => LeadActivity::TYPE_CREATED,
                'to_status_id' => $statusId,
                'note' => $data['notes'] ?? null,
            ]);

            return $lead;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data, User $user): Lead
    {
        $this->assertNotCancelled($lead);

        if (array_key_exists('phone', $data) && LeadPhone::canonical((string) $data['phone']) !== $lead->phone_digits) {
            $this->assertPhoneAvailable((int) $lead->clinic_id, (string) $data['phone'], $lead);
        }

        return DB::transaction(function () use ($lead, $data, $user): Lead {
            $previousStatusId = $lead->lead_status_id;
            $previousNotes = $lead->notes;

            if (array_key_exists('lead_status_id', $data) && $data['lead_status_id'] === null) {
                unset($data['lead_status_id']);
            }

            $lead->fill($data);
            $lead->save();

            if ((int) $previousStatusId !== (int) $lead->lead_status_id) {
                $lead->activities()->create([
                    'user_id' => $user->id,
                    'type' => LeadActivity::TYPE_STATUS_CHANGED,
                    'from_status_id' => $previousStatusId,
                    'to_status_id' => $lead->lead_status_id,
                    'note' => $lead->notes !== $previousNotes ? $lead->notes : null,
                ]);
            } elseif ($lead->notes !== $previousNotes && filled($lead->notes)) {
                $lead->activities()->create([
                    'user_id' => $user->id,
                    'type' => LeadActivity::TYPE_NOTE,
                    'note' => $lead->notes,
                ]);
            }

            return $lead;
        });
    }

    public function changeStatus(Lead $lead, int $statusId, ?string $note, User $user): Lead
    {
        $this->assertNotCancelled($lead);

        return DB::transaction(function () use ($lead, $statusId, $note, $user): Lead {
            $previous = $lead->lead_status_id;
            $note = filled($note) ? trim((string) $note) : null;

            if ((int) $previous === $statusId && $note === null) {
                return $lead;
            }

            $lead->lead_status_id = $statusId;
            if ($note !== null) {
                $lead->notes = $note;
            }
            $lead->save();

            $lead->activities()->create([
                'user_id' => $user->id,
                'type' => (int) $previous === $statusId ? LeadActivity::TYPE_NOTE : LeadActivity::TYPE_STATUS_CHANGED,
                'from_status_id' => (int) $previous === $statusId ? null : $previous,
                'to_status_id' => (int) $previous === $statusId ? null : $statusId,
                'note' => $note,
            ]);

            return $lead;
        });
    }

    /**
     * Active patients of the lead clinic with the same phone digits.
     *
     * @return Collection<int, Client>
     */
    public function matchingClients(Lead $lead): Collection
    {
        $candidates = LeadPhone::clientCandidates((string) $lead->phone);
        if ($candidates === []) {
            return new Collection;
        }

        return Client::query()
            ->where('clinic_id', $lead->clinic_id)
            ->whereIn('whatsapp', $candidates)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(5)
            ->get();
    }

    /**
     * Turns the lead into a patient (new or linked) and books the first visit.
     * The lead is kept, marked with the system "Virou paciente" status and linked to the patient.
     *
     * @param  array{scheduled_at: string, professional_user_id: int, duration_minutes?: int|null, notes?: string|null, client_id?: int|null, create_new_client?: bool}  $data
     * @return array{lead: Lead, client: Client, appointment: Appointment, client_created: bool}
     */
    public function convert(Lead $lead, array $data, User $user): array
    {
        $this->assertNotCancelled($lead);

        if ($lead->isConverted()) {
            throw ValidationException::withMessages([
                'lead' => ['Este lead já virou paciente.'],
            ]);
        }

        $clientId = $data['client_id'] ?? null;
        $createNew = (bool) ($data['create_new_client'] ?? false);

        if ($clientId === null && ! $createNew) {
            $matches = $this->matchingClients($lead);
            if ($matches->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'client_id' => ['Já existe paciente com este telefone. Vincule ao paciente existente ou crie um novo.'],
                ]);
            }
        }

        return DB::transaction(function () use ($lead, $data, $user, $clientId): array {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($locked->isConverted()) {
                throw ValidationException::withMessages([
                    'lead' => ['Este lead já virou paciente.'],
                ]);
            }
            $this->assertNotCancelled($locked);

            $clientCreated = false;

            if ($clientId !== null) {
                /** @var Client $client */
                $client = Client::query()->whereKey($clientId)->firstOrFail();
                $updates = [];
                if ($client->lead_id === null) {
                    $updates['lead_id'] = $locked->id;
                }
                if ($client->client_origin_id === null) {
                    $updates['client_origin_id'] = $locked->client_origin_id;
                }
                if (! $client->is_active) {
                    $updates['is_active'] = true;
                }
                if ($updates !== []) {
                    $client->update($updates);
                }
            } else {
                $client = Client::query()->create([
                    'clinic_id' => $locked->clinic_id,
                    'name' => $locked->name,
                    'whatsapp' => $locked->phone,
                    'client_origin_id' => $locked->client_origin_id,
                    'lead_id' => $locked->id,
                    'is_active' => true,
                ]);
                $clientCreated = true;
            }

            $result = $this->appointments->scheduleEvaluation($client, [
                'scheduled_at' => $data['scheduled_at'],
                'professional_user_id' => (int) $data['professional_user_id'],
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $converted = EnsureDefaultLeadStatuses::convertedFor((int) $locked->clinic_id);
            $previousStatusId = $locked->lead_status_id;

            $locked->update([
                'client_id' => $client->id,
                'lead_status_id' => $converted->id,
                'converted_at' => now(),
            ]);

            $locked->activities()->create([
                'user_id' => $user->id,
                'type' => LeadActivity::TYPE_CONVERTED,
                'from_status_id' => $previousStatusId,
                'to_status_id' => $converted->id,
                'note' => $data['notes'] ?? null,
            ]);

            return [
                'lead' => $locked,
                'client' => $client,
                'appointment' => $result['appointment'],
                'client_created' => $clientCreated,
            ];
        });
    }

    /**
     * Cancelling is final: the lead stays in the list, history and metrics, never deleted.
     */
    public function cancel(Lead $lead, ?string $reason, User $user): Lead
    {
        return DB::transaction(function () use ($lead, $reason, $user): Lead {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $this->assertNotCancelled($locked);
            if ($locked->isConverted()) {
                throw ValidationException::withMessages([
                    'lead' => ['Lead que virou paciente não pode ser cancelado.'],
                ]);
            }

            $cancelled = EnsureDefaultLeadStatuses::cancelledFor((int) $locked->clinic_id);
            $previousStatusId = $locked->lead_status_id;
            $reason = filled($reason) ? trim((string) $reason) : null;

            $locked->update([
                'lead_status_id' => $cancelled->id,
                'cancelled_at' => now(),
            ]);

            $locked->activities()->create([
                'user_id' => $user->id,
                'type' => LeadActivity::TYPE_CANCELLED,
                'from_status_id' => $previousStatusId,
                'to_status_id' => $cancelled->id,
                'note' => $reason,
            ]);

            return $locked;
        });
    }

    /**
     * First conflict for this phone in the clinic: another non-cancelled lead, then a patient.
     * Cancelled leads never block a new lead.
     *
     * @return array<string, mixed>|null
     */
    public function phoneConflict(int $clinicId, string $phone, ?Lead $ignore = null): ?array
    {
        $canonical = LeadPhone::canonical($phone);
        if ($canonical === '') {
            return null;
        }

        $lead = Lead::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('phone_digits', $canonical)
            ->whereNull('cancelled_at')
            ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
            ->with(['status', 'createdBy', 'client'])
            ->orderBy('id')
            ->first();

        if ($lead !== null) {
            $by = $lead->createdBy?->name;
            $when = $lead->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y');

            $leadClient = $lead->client;

            return [
                'type' => $leadClient !== null ? 'patient' : 'lead',
                'message' => $leadClient !== null ? sprintf(
                    'Este telefone já é do paciente %s (veio do lead cadastrado%s em %s).',
                    $leadClient->name,
                    $by !== null ? ' por '.$by : '',
                    $when ?? '—',
                ) : sprintf(
                    'Já existe o lead %s com este telefone (status %s, cadastrado%s em %s).',
                    $lead->name,
                    $lead->status?->name ?? '—',
                    $by !== null ? ' por '.$by : '',
                    $when ?? '—',
                ),
                'lead' => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'status' => $lead->status === null ? null : [
                        'id' => $lead->status->id,
                        'name' => $lead->status->name,
                        'color' => $lead->status->color,
                        'system_key' => $lead->status->system_key,
                    ],
                    'created_by' => $lead->createdBy === null ? null : [
                        'id' => $lead->createdBy->id,
                        'name' => $lead->createdBy->name,
                    ],
                    'created_at' => $lead->created_at?->toIso8601String(),
                ],
                'client' => $leadClient === null ? null : [
                    'id' => $leadClient->id,
                    'name' => $leadClient->name,
                    'is_active' => (bool) $leadClient->is_active,
                ],
            ];
        }

        $client = Client::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->whereIn('whatsapp', LeadPhone::clientCandidates($phone))
            ->when($ignore?->client_id !== null, fn ($q) => $q->whereKeyNot($ignore->client_id))
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        if ($client !== null) {
            return [
                'type' => 'patient',
                'message' => sprintf('Este telefone já é do paciente %s.', $client->name),
                'lead' => null,
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'is_active' => (bool) $client->is_active,
                ],
            ];
        }

        return null;
    }

    private function assertPhoneAvailable(int $clinicId, string $phone, ?Lead $ignore): void
    {
        $conflict = $this->phoneConflict($clinicId, $phone, $ignore);
        if ($conflict === null) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => $conflict['message'],
            'errors' => ['phone' => [$conflict['message']]],
            'duplicate' => $conflict,
        ], 422));
    }

    private function assertNotCancelled(Lead $lead): void
    {
        if ($lead->isCancelled()) {
            throw ValidationException::withMessages([
                'lead' => ['Lead cancelado é definitivo e não pode ser alterado.'],
            ]);
        }
    }
}
