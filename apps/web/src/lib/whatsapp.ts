/** WhatsApp via wa.me deep links (no paid API). */

export type WhatsAppVars = Partial<
  Record<'paciente' | 'clinica' | 'data' | 'hora' | 'profissional' | 'usuario', string>
>

/** Digits with the Brazilian country code (55) added when missing. */
export function whatsappDigits(phone: string | null | undefined): string {
  const digits = String(phone ?? '').replace(/\D+/g, '')
  if (digits.length < 10) {
    return ''
  }
  if (digits.startsWith('55') && digits.length >= 12) {
    return digits
  }
  return `55${digits}`
}

export function firstName(name: string | null | undefined): string {
  return String(name ?? '').trim().split(/\s+/)[0] ?? ''
}

/**
 * Replaces {placeholders}. An empty {profissional} also drops a leading "com " so the
 * sentence still reads well ("às 14:00 com {profissional}." → "às 14:00.").
 */
export function renderWhatsAppTemplate(template: string, vars: WhatsAppVars): string {
  let text = template
  if (!vars.profissional) {
    text = text.replace(/\s*\bcom\s+\{profissional\}/giu, '')
  }
  text = text.replace(/\{(paciente|clinica|data|hora|profissional|usuario)\}/giu, (_, key: string) => {
    return vars[key.toLowerCase() as keyof WhatsAppVars] ?? ''
  })
  return text.replace(/[ \t]{2,}/g, ' ').trim()
}

export function whatsappUrl(phone: string | null | undefined, text: string): string | null {
  const digits = whatsappDigits(phone)
  if (!digits) {
    return null
  }
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
}

/** dd/mm and HH:mm in the clinic timezone. */
export function whatsappDateParts(iso: string | null | undefined): { data: string; hora: string } {
  if (!iso) {
    return { data: '', hora: '' }
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return { data: '', hora: '' }
  }
  const zone = { timeZone: 'America/Sao_Paulo' } as const
  return {
    data: new Intl.DateTimeFormat('pt-BR', { ...zone, day: '2-digit', month: '2-digit' }).format(date),
    hora: new Intl.DateTimeFormat('pt-BR', { ...zone, hour: '2-digit', minute: '2-digit' }).format(date),
  }
}

/** Opens the link in the click handler itself so mobile browsers do not block it. */
export function openWhatsApp(url: string): void {
  window.open(url, '_blank', 'noopener')
}
