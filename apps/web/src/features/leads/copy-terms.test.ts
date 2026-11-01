import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { describe, expect, it } from 'vitest'
import { activityLabel } from '@/features/leads/labels'
import type { LeadActivity } from '@/types/lead'

const SRC = join(__dirname, '..', '..')

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return files(path)
    return /\.(vue|ts)$/.test(name) && !/\.test\.ts$/.test(name) ? [path] : []
  })
}

describe('termo do agendamento: "consulta", nunca "avaliação"', () => {
  it('não aparece "avaliação" no app', () => {
    const hits = files(SRC).filter((path) => /avalia[çc][ãaõo]/iu.test(readFileSync(path, 'utf8')))
    expect(hits.map((path) => relative(SRC, path))).toEqual([])
  })

  it('histórico do lead convertido fala em consulta agendada', () => {
    expect(activityLabel({ type: 'converted' } as LeadActivity)).toBe('Virou paciente (consulta agendada)')
  })
})
