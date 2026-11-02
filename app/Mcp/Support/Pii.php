<?php

namespace App\Mcp\Support;

/**
 * Proteção de dados pessoais nas respostas do conector MCP (Claude).
 *
 * Regra: nenhuma ferramenta devolve telefone/WhatsApp completo, e-mail, CPF,
 * endereço, data de nascimento nem anotações clínicas de leads e pacientes.
 * As ferramentas já não incluem esses campos; scrub() é a última barreira,
 * aplicada ao texto final de TODA ferramenta (HofPayTool::handle).
 */
class Pii
{
    /**
     * Sequência que parece telefone: 10 a 13 dígitos, com ou sem +, espaços, pontos,
     * hífens ou parênteses entre eles. Valores em R$ (com vírgula), datas (com /) e
     * horas (com :) não casam.
     */
    public const PHONE_REGEX = '/(?<![\d,])\+?\(?\d(?:[\s().\-]{0,2}\d){9,12}(?![\d,])/u';

    public const EMAIL_REGEX = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu';

    /** CPF com pontuação (000.000.000-00). Sem pontuação, 11 dígitos já caem em PHONE_REGEX. */
    public const CPF_REGEX = '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/u';

    /** "11987651234" → "(11) 9****-1234". Só os 4 últimos dígitos ficam visíveis. */
    public static function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return '—';
        }
        if (strlen($digits) < 8) {
            return '****';
        }
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }
        $last = substr($digits, -4);
        $ddd = strlen($digits) >= 10 ? '('.substr($digits, 0, 2).') ' : '';
        $mobile = strlen($digits) === 11 ? '9' : '';

        return $ddd.$mobile.'****-'.$last;
    }

    /** "maria@clinica.com" → "m***@c***.com". */
    public static function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '—';
        }
        [$local, $domain] = explode('@', $email, 2);
        $tld = str_contains($domain, '.') ? substr($domain, strrpos($domain, '.')) : '';

        return mb_substr($local, 0, 1).'***@'.mb_substr($domain, 0, 1).'***'.$tld;
    }

    /** Última barreira: mascara qualquer telefone, e-mail ou CPF que sobrar no texto. */
    public static function scrub(string $text): string
    {
        $text = preg_replace_callback(self::EMAIL_REGEX, fn ($m) => self::maskEmail($m[0]), $text) ?? $text;
        $text = preg_replace(self::CPF_REGEX, '***.***.***-**', $text) ?? $text;

        return preg_replace_callback(self::PHONE_REGEX, fn ($m) => self::maskPhone($m[0]), $text) ?? $text;
    }

    /**
     * Buscas do MCP são só por nome: números são recusados para ninguém
     * descobrir dono de telefone tentando combinações (enumeração).
     */
    public static function assertNameSearch(mixed $term, string $field = 'busca'): ?string
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }
        if (preg_match('/\d/', $term) === 1 || str_contains($term, '@')) {
            throw new McpToolException("A {$field} aceita só nome (sem números, telefone ou e-mail).");
        }

        return mb_substr($term, 0, 80);
    }
}
