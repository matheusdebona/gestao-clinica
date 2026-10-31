<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Support\EnsureDefaultLeadStatuses;
use Illuminate\Database\Eloquent\Collection;
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
        $digits = preg_replace('/\D+/', '', $lead->phone) ?? '';
        if ($digits === '') {
            return new Collection;
        }

        $candidates = [$digits];
        // Accept stored numbers with/without the 55 country code.
        if (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $candidates[] = substr($digits, 2);
        } else {
            $candidates[] = '55'.$digits;
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
}
