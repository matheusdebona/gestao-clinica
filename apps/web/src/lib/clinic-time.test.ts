import { describe, expect, it } from 'vitest'
import {
  clinicDateOf,
  clinicDatetimeLocalToIso,
  clinicDayEndIso,
  clinicDayStartIso,
  clinicMonthIso,
  clinicTodayIso,
  clinicWallClock,
  isoToClinicDatetimeLocal,
} from '@/lib/clinic-time'
import { formatDate, formatDateTime, formatIsoDate, formatTime } from '@/lib/formatters'
import { currentMonthRange, lastDaysRange } from '@/features/metrics/period'
import { todayIso } from '@/lib/iso-date'

// Os testes rodam com TZ=Asia/Tokyo (vitest.config.ts): nada pode depender do fuso do aparelho.
// 31/10 23:30 em Brasília = 01/11 02:30 UTC.
const OCT_31_2330_SP = '2026-11-01T02:30:00Z'

describe('clinic time (America/Sao_Paulo)', () => {
  it('runs in a non-Brasília device timezone', () => {
    expect(new Date(OCT_31_2330_SP).getTimezoneOffset()).toBe(-540) // aparelho em Tóquio (UTC+9)
  })

  it('takes today and the month from the São Paulo calendar at 23:30', () => {
    const now = new Date(OCT_31_2330_SP)
    expect(clinicTodayIso(now)).toBe('2026-10-31')
    expect(todayIso(now)).toBe('2026-10-31')
    expect(clinicMonthIso(now)).toBe('2026-10')
    expect(currentMonthRange(now)).toEqual({ from: '2026-10-01', to: '2026-10-31' })
    expect(lastDaysRange(7, now)).toEqual({ from: '2026-10-25', to: '2026-10-31' })
    const wall = clinicWallClock(now)
    expect([wall.getDate(), wall.getHours(), wall.getMinutes()]).toEqual([31, 23, 30])
    // 00:00 de 01/11 em Brasília já é novembro.
    expect(clinicMonthIso(new Date('2026-11-01T03:00:00Z'))).toBe('2026-11')
  })

  it('shows instants as São Paulo dates and times', () => {
    expect(formatDate(OCT_31_2330_SP)).toBe('31/10/2026')
    expect(formatTime(OCT_31_2330_SP)).toBe('23:30')
    expect(formatDateTime(OCT_31_2330_SP)).toBe('31/10/2026, 23:30')
    expect(clinicDateOf(OCT_31_2330_SP)).toBe('2026-10-31')
    // Datas de calendário não mudam de dia.
    expect(formatDate('2026-10-31')).toBe('31/10/2026')
    expect(formatIsoDate('2026-10-31T00:00:00.000000Z')).toBe('31/10/2026')
    expect(clinicDateOf('2026-10-31')).toBe('2026-10-31')
  })

  it('sends São Paulo day bounds and reads typed times as Brasília time', () => {
    expect(clinicDayStartIso('2026-10-31')).toBe('2026-10-31T03:00:00.000Z')
    expect(clinicDayEndIso('2026-10-31')).toBe('2026-11-01T02:59:59.999Z')
    expect(clinicDatetimeLocalToIso('2026-10-31T23:30')).toBe('2026-11-01T02:30:00.000Z')
    expect(isoToClinicDatetimeLocal(OCT_31_2330_SP)).toBe('2026-10-31T23:30')
  })
})
