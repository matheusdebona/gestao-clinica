<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Support\ClinicSettings;
use App\Support\CurrentClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClinicSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->currentClinic())]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'whatsapp_templates' => ['sometimes', 'array'],
            'whatsapp_templates.'.ClinicSettings::TEMPLATE_APPOINTMENT_CONFIRMATION => ['sometimes', 'nullable', 'string', 'max:1000'],
            'whatsapp_templates.'.ClinicSettings::TEMPLATE_LEAD_FOLLOW_UP => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lead_stale_days' => ['sometimes', 'nullable', 'integer', 'min:'.ClinicSettings::MIN_LEAD_STALE_DAYS, 'max:'.ClinicSettings::MAX_LEAD_STALE_DAYS],
        ], [
            'lead_stale_days.integer' => 'Informe um número inteiro de dias.',
            'lead_stale_days.min' => 'Use pelo menos '.ClinicSettings::MIN_LEAD_STALE_DAYS.' dia.',
            'lead_stale_days.max' => 'Use no máximo '.ClinicSettings::MAX_LEAD_STALE_DAYS.' dias.',
            'whatsapp_templates.*.max' => 'Mensagem muito longa (máximo de 1000 caracteres).',
        ]);

        $clinic = $this->currentClinic();

        if (array_key_exists('whatsapp_templates', $data)) {
            ClinicSettings::updateWhatsappTemplates($clinic, $data['whatsapp_templates']);
        }

        if (array_key_exists('lead_stale_days', $data)) {
            ClinicSettings::updateLeadStaleDays($clinic, $data['lead_stale_days'] !== null ? (int) $data['lead_stale_days'] : null);
            $clinic->refresh();
        }

        return response()->json(['data' => $this->payload($clinic->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Clinic $clinic): array
    {
        return [
            'clinic_name' => $clinic->name,
            'whatsapp_templates' => ClinicSettings::whatsappTemplates($clinic),
            'whatsapp_template_defaults' => ClinicSettings::DEFAULT_WHATSAPP_TEMPLATES,
            'whatsapp_placeholders' => ClinicSettings::WHATSAPP_PLACEHOLDERS,
            'lead_stale_days' => ClinicSettings::leadStaleDays($clinic),
            'lead_stale_days_default' => ClinicSettings::DEFAULT_LEAD_STALE_DAYS,
        ];
    }

    private function currentClinic(): Clinic
    {
        $clinicId = CurrentClinic::id();

        if ($clinicId === null) {
            throw new NotFoundHttpException('No clinic bound to this user.');
        }

        return Clinic::query()->findOrFail($clinicId);
    }
}
