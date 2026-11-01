/**
 * Horário de Brasília (America/Sao_Paulo) para datas na tela e nos filtros.
 *
 * A API grava instantes em UTC; dias, semanas e meses ("hoje", "este mês", filtros de período)
 * são sempre os do calendário de São Paulo, qualquer que seja o fuso do aparelho.
 */
export const CLINIC_TZ = 'America/Sao_Paulo'

export interface ClinicParts {
  year: number
  month: number
  day: number
  hour: number
  minute: number
  second: number
}

const partsFormat = new Intl.DateTimeFormat('en-US', {
  timeZone: CLINIC_TZ,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hourCycle: 'h23',
})

const pad = (n: number) => String(n).padStart(2, '0')

/** Campos de calendário e relógio do instante em São Paulo. */
export function clinicParts(date: Date): ClinicParts {
  const out: Record<string, number> = {}
  for (const part of partsFormat.formatToParts(date)) {
    if (part.type !== 'literal') {
      out[part.type] = Number(part.value)
    }
  }
  return {
    year: out.year!,
    month: out.month!,
    day: out.day!,
    hour: out.hour === 24 ? 0 : out.hour!,
    minute: out.minute!,
    second: out.second!,
  }
}

/**
 * Data cujos campos LOCAIS (getFullYear, getDate, getHours…) são o relógio de São Paulo. Serve
 * para contas de calendário (início do mês, ±dias) com as funções locais do Date.
 */
export function clinicWallClock(now: Date = new Date()): Date {
  const p = clinicParts(now)
  return new Date(p.year, p.month - 1, p.day, p.hour, p.minute, p.second)
}

/** Hoje em São Paulo ("2026-10-31"). */
export function clinicTodayIso(now: Date = new Date()): string {
  const p = clinicParts(now)
  return `${p.year}-${pad(p.month)}-${pad(p.day)}`
}

/** Mês atual em São Paulo ("2026-10"). */
export function clinicMonthIso(now: Date = new Date()): string {
  return clinicTodayIso(now).slice(0, 7)
}

/** Diferença (minutos) entre o relógio de São Paulo e UTC no instante (ex.: -180). */
export function clinicOffsetMinutes(date: Date): number {
  const p = clinicParts(date)
  const asUtc = Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute, p.second)
  return Math.round((asUtc - Math.floor(date.getTime() / 1000) * 1000) / 60000)
}

/** Instante do relógio de São Paulo (dateIso "Y-m-d" às hh:mm:ss.ms). */
export function clinicInstant(dateIso: string, hours = 0, minutes = 0, seconds = 0, ms = 0): Date {
  const [y, m, d] = dateIso.split('-').map(Number)
  const guess = Date.UTC(y!, m! - 1, d!, hours, minutes, seconds, ms)
  let offset = clinicOffsetMinutes(new Date(guess))
  let result = guess - offset * 60000
  const corrected = clinicOffsetMinutes(new Date(result))
  if (corrected !== offset) {
    offset = corrected
    result = guess - offset * 60000
  }
  return new Date(result)
}

/** Início (ISO, UTC) do dia de São Paulo. */
export function clinicDayStartIso(dateIso: string): string {
  return clinicInstant(dateIso).toISOString()
}

/** Fim (ISO, UTC) do dia de São Paulo. */
export function clinicDayEndIso(dateIso: string): string {
  return clinicInstant(dateIso, 23, 59, 59, 999).toISOString()
}

/**
 * Data de calendário de São Paulo de um valor da API: data pura ("2026-10-31") fica como está;
 * instante (ISO com hora) vira o dia em São Paulo.
 */
export function clinicDateOf(value: string | Date | null | undefined): string | null {
  if (!value) {
    return null
  }
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
    return value.trim()
  }
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) {
    return null
  }
  return clinicTodayIso(date)
}

/** "Y-m-dTHH:mm" no relógio de São Paulo → ISO (UTC). */
export function clinicDatetimeLocalToIso(value: string): string {
  const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?/.exec(value.trim())
  if (!match) {
    return new Date(value).toISOString()
  }
  return clinicInstant(match[1]!, Number(match[2]), Number(match[3]), Number(match[4] ?? 0)).toISOString()
}

/** ISO (UTC) → "Y-m-dTHH:mm" no relógio de São Paulo. */
export function isoToClinicDatetimeLocal(value: string | null | undefined): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  const p = clinicParts(date)
  return `${p.year}-${pad(p.month)}-${pad(p.day)}T${pad(p.hour)}:${pad(p.minute)}`
}
