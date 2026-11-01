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
        $rules = ['whatsapp_templates' => ['sometimes', 'array']];
        foreach (array_keys(ClinicSettings::DEFAULT_WHATSAPP_TEMPLATES) as $key) {
            $rules['whatsapp_templates.'.$key] = ['sometimes', 'nullable', 'string', 'max:1000'];
        }
        $rules['budget_follow_up_days'] = ['sometimes', 'array', 'max:'.ClinicSettings::MAX_BUDGET_FOLLOW_UP_TIERS];
        $rules['budget_follow_up_days.*'] = ['integer', 'min:1', 'max:365', 'distinct'];
        $data = $request->validate($rules, [
            'whatsapp_templates.*.max' => 'Mensagem muito longa (máximo de 1000 caracteres).',
            'budget_follow_up_days.max' => 'Use no máximo '.ClinicSettings::MAX_BUDGET_FOLLOW_UP_TIERS.' prazos.',
            'budget_follow_up_days.*.integer' => 'Informe dias inteiros.',
            'budget_follow_up_days.*.min' => 'Informe de 1 a 365 dias.',
            'budget_follow_up_days.*.max' => 'Informe de 1 a 365 dias.',
            'budget_follow_up_days.*.distinct' => 'Não repita o mesmo prazo.',
        ]);

        $clinic = $this->currentClinic();

        if (array_key_exists('whatsapp_templates', $data)) {
            ClinicSettings::updateWhatsappTemplates($clinic, $data['whatsapp_templates']);
        }

        if (array_key_exists('budget_follow_up_days', $data)) {
            ClinicSettings::updateBudgetFollowUpDays($clinic, $data['budget_follow_up_days']);
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
            'budget_follow_up_days' => ClinicSettings::budgetFollowUpDays($clinic),
            'budget_follow_up_days_default' => ClinicSettings::DEFAULT_BUDGET_FOLLOW_UP_DAYS,
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
