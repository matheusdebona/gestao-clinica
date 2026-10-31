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

    /** @var array<string, string> */
    public const DEFAULT_WHATSAPP_TEMPLATES = [
        self::TEMPLATE_APPOINTMENT_CONFIRMATION => 'Olá, {paciente}! Aqui é da {clinica}. Passando para confirmar seu horário no dia {data} às {hora} com {profissional}. Podemos confirmar?',
        self::TEMPLATE_LEAD_FOLLOW_UP => 'Olá, {paciente}! Aqui é {usuario}, da {clinica}. Vi seu interesse e queria saber se posso te ajudar a agendar uma avaliação.',
    ];

    /** @var array<string, list<string>> */
    public const WHATSAPP_PLACEHOLDERS = [
        self::TEMPLATE_APPOINTMENT_CONFIRMATION => ['{paciente}', '{clinica}', '{data}', '{hora}', '{profissional}', '{usuario}'],
        self::TEMPLATE_LEAD_FOLLOW_UP => ['{paciente}', '{clinica}', '{usuario}'],
    ];

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
