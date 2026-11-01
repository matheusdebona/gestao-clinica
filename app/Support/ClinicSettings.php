<?php

namespace App\Support;

use App\Models\Clinic;

/**
 * Per-clinic preferences stored in clinics.settings (JSON). Defaults live here, so every
 * clinic (existing or new) starts with sensible values without a data migration.
 */
class ClinicSettings
{
    public const TEMPLATE_APPOINTMENT_CONFIRMATION = 'appointment_confirmation';

    public const TEMPLATE_LEAD_FOLLOW_UP = 'lead_follow_up';

    public const TEMPLATE_PATIENT_RETURN = 'patient_return';

    public const TEMPLATE_BUDGET_FOLLOW_UP = 'budget_follow_up';

    /** Orçamento enviado sem resposta há N dias (prazos crescentes, como os alertas de leads parados). */
    public const DEFAULT_BUDGET_FOLLOW_UP_DAYS = [2, 5, 10];

    public const MAX_BUDGET_FOLLOW_UP_TIERS = 5;

    /** @var array<string, string> */
    public const DEFAULT_WHATSAPP_TEMPLATES = [
        self::TEMPLATE_APPOINTMENT_CONFIRMATION => 'Olá, {paciente}! Aqui é da {clinica}. Passando para confirmar seu horário no dia {data} às {hora} com {profissional}. Podemos confirmar?',
        self::TEMPLATE_LEAD_FOLLOW_UP => 'Olá, {paciente}! Aqui é {usuario}, da {clinica}. Vi seu interesse e queria saber se posso te ajudar a agendar uma avaliação.',
        self::TEMPLATE_PATIENT_RETURN => 'Olá, {paciente}! Aqui é {usuario}, da {clinica}. Já está chegando a hora do seu retorno de {procedimento} (previsto para {data}). Vamos agendar?',
        self::TEMPLATE_BUDGET_FOLLOW_UP => 'Olá, {paciente}! Aqui é {usuario}, da {clinica}. Passando para saber se ficou alguma dúvida sobre o orçamento de {valor} que enviamos em {data}. Posso te ajudar a fechar?',
    ];

    /** @var array<string, list<string>> */
    public const WHATSAPP_PLACEHOLDERS = [
        self::TEMPLATE_APPOINTMENT_CONFIRMATION => ['{paciente}', '{clinica}', '{data}', '{hora}', '{profissional}', '{usuario}'],
        self::TEMPLATE_LEAD_FOLLOW_UP => ['{paciente}', '{clinica}', '{usuario}'],
        self::TEMPLATE_PATIENT_RETURN => ['{paciente}', '{clinica}', '{usuario}', '{procedimento}', '{data}'],
        self::TEMPLATE_BUDGET_FOLLOW_UP => ['{paciente}', '{clinica}', '{usuario}', '{valor}', '{data}'],
    ];

    /**
     * @return list<int> ascending, distinct
     */
    public static function budgetFollowUpDays(Clinic $clinic): array
    {
        $stored = $clinic->settings['budget_follow_up_days'] ?? null;
        if (! is_array($stored)) {
            return self::DEFAULT_BUDGET_FOLLOW_UP_DAYS;
        }

        return self::normalizeDays($stored);
    }

    /**
     * @param  list<int|string>  $days  empty = disables forgotten-budget alerts
     */
    public static function updateBudgetFollowUpDays(Clinic $clinic, array $days): void
    {
        $settings = $clinic->settings ?? [];
        $settings['budget_follow_up_days'] = self::normalizeDays($days);
        $clinic->settings = $settings;
        $clinic->save();
    }

    /**
     * @param  array<int|string>  $days
     * @return list<int>
     */
    private static function normalizeDays(array $days): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map('intval', $days),
            fn (int $day): bool => $day >= 1 && $day <= 365,
        )));
        sort($clean);

        return array_slice($clean, 0, self::MAX_BUDGET_FOLLOW_UP_TIERS);
    }

    /**
     * @return array<string, string>
     */
    public static function whatsappTemplates(Clinic $clinic): array
    {
        $stored = $clinic->settings['whatsapp_templates'] ?? [];
        $stored = is_array($stored) ? $stored : [];
        $templates = [];

        foreach (self::DEFAULT_WHATSAPP_TEMPLATES as $key => $default) {
            $value = $stored[$key] ?? null;
            $templates[$key] = is_string($value) && trim($value) !== '' ? $value : $default;
        }

        return $templates;
    }

    /**
     * @param  array<string, string|null>  $templates  null/blank = back to the default
     */
    public static function updateWhatsappTemplates(Clinic $clinic, array $templates): void
    {
        $settings = $clinic->settings ?? [];
        $current = is_array($settings['whatsapp_templates'] ?? null) ? $settings['whatsapp_templates'] : [];

        foreach (array_keys(self::DEFAULT_WHATSAPP_TEMPLATES) as $key) {
            if (! array_key_exists($key, $templates)) {
                continue;
            }
            $value = $templates[$key];
            if ($value === null || trim($value) === '') {
                unset($current[$key]);
            } else {
                $current[$key] = trim($value);
            }
        }

        $settings['whatsapp_templates'] = $current;
        $clinic->settings = $settings;
        $clinic->save();
    }
}
