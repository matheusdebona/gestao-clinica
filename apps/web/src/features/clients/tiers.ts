import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import type { BadgeVariant } from '@/components/ui/badge-variants'
import { api } from '@/lib/api'
import { formatBRL } from '@/lib/formatters'
import type { DataEnvelope } from '@/types/pagination'

/** Paleta fixa das faixas (mesma lista do backend: PatientTier::COLORS). */
export type PatientTierColor = 'bronze' | 'silver' | 'gold' | 'diamond' | 'purple' | 'success' | 'muted'

export interface PatientTier {
  id: number
  name: string
  color: PatientTierColor | null
  min_amount: string
  /** null = faixa do topo, sem limite. */
  max_amount: string | null
  range_label: string
}

export interface PatientTierRef {
  id: number
  name: string
  color: PatientTierColor | null
}

export interface PatientTierPayload {
  name: string
  color: PatientTierColor | null
  min_amount: string
}

export const PATIENT_TIER_COLOR_OPTIONS: { value: PatientTierColor; label: string }[] = [
  { value: 'bronze', label: 'Bronze' },
  { value: 'silver', label: 'Prata' },
  { value: 'gold', label: 'Ouro' },
  { value: 'diamond', label: 'Diamante (azul-claro)' },
  { value: 'purple', label: 'Roxo' },
  { value: 'success', label: 'Verde' },
  { value: 'muted', label: 'Cinza' },
]

export const PATIENT_TIERS_KEY = ['patient-tiers'] as const

/** Valor do filtro para "Sem faixa". Lista vazia = Todos. */
export const TIER_FILTER_NONE = 'none'
export type TierFilterToken = number | typeof TIER_FILTER_NONE

export function tierBadgeVariant(color: PatientTierColor | null | undefined): BadgeVariant {
  return color ?? 'muted'
}

export async function listPatientTiers(): Promise<PatientTier[]> {
  const payload = await api<DataEnvelope<PatientTier[]>>('/patient-tiers')
  return payload.data
}

export async function createPatientTier(body: PatientTierPayload): Promise<PatientTier> {
  const payload = await api<DataEnvelope<PatientTier>>('/patient-tiers', { method: 'POST', body })
  return payload.data
}

export async function updatePatientTier(id: number, body: PatientTierPayload): Promise<PatientTier> {
  const payload = await api<DataEnvelope<PatientTier>>(`/patient-tiers/${id}`, { method: 'PUT', body })
  return payload.data
}

export async function deletePatientTier(id: number): Promise<void> {
  await api(`/patient-tiers/${id}`, { method: 'DELETE' })
}

export async function restoreDefaultPatientTiers(): Promise<PatientTier[]> {
  const payload = await api<DataEnvelope<PatientTier[]>>('/patient-tiers/restore-defaults', { method: 'POST' })
  return payload.data
}

export function usePatientTiers(enabled = computed(() => true)) {
  const query = useQuery({
    queryKey: PATIENT_TIERS_KEY,
    queryFn: listPatientTiers,
    staleTime: 5 * 60_000,
    enabled,
  })
  const tiers = computed(() =>
    [...(query.data.value ?? [])].sort((a, b) => Number(a.min_amount) - Number(b.min_amount)),
  )
  return { ...query, tiers }
}

function toCents(value: string | number): number {
  return Math.round(Number(value) * 100)
}

/** Mesmo texto do backend: "R$ 0,00 a R$ 5.000,00", "Acima de R$ 20.000,00". */
export function rangeLabel(minCents: number, nextCents: number | null): string {
  if (nextCents === null) {
    return minCents === 0 ? 'Qualquer valor' : `Acima de ${formatBRL((minCents - 1) / 100)}`
  }
  return `${formatBRL(minCents / 100)} a ${formatBRL((nextCents - 1) / 100)}`
}

/**
 * Prévia da faixa no modal: como fica a faixa editada, dado o mínimo digitado e as outras faixas.
 * Retorna null se o valor for inválido ou repetir o mínimo de outra faixa.
 */
export function previewRange(
  minAmount: string,
  others: Pick<PatientTier, 'min_amount'>[],
): { label: string; belowLowest: string | null } | null {
  if (minAmount.trim() === '' || !Number.isFinite(Number(minAmount)) || Number(minAmount) < 0) {
    return null
  }
  const min = toCents(minAmount)
  const mins = others.map((tier) => toCents(tier.min_amount))
  if (mins.includes(min)) {
    return null
  }
  const next = mins.filter((value) => value > min).sort((a, b) => a - b)[0] ?? null
  const lowest = Math.min(min, ...mins)
  return {
    label: rangeLabel(min, next),
    belowLowest: lowest > 0 ? `Abaixo de ${formatBRL(lowest / 100)}: sem faixa` : null,
  }
}

/** Query string do filtro de faixa: [] = Todos (nenhum parâmetro). */
export function tierFilterQuery(tokens: TierFilterToken[]): string | undefined {
  return tokens.length ? tokens.map(String).join(',') : undefined
}

/** Remove do filtro faixas que não existem mais (ex.: excluída nas configurações). */
export function sanitizeTierFilter(tokens: TierFilterToken[], tiers: Pick<PatientTier, 'id'>[]): TierFilterToken[] {
  const ids = new Set(tiers.map((tier) => tier.id))
  return tokens.filter((token) => token === TIER_FILTER_NONE || ids.has(token))
}

export function tierFilterSummary(tokens: TierFilterToken[], tiers: Pick<PatientTier, 'id' | 'name'>[]): string {
  if (tokens.length === 0) {
    return 'Todos'
  }
  const names = tokens.map((token) =>
    token === TIER_FILTER_NONE ? 'Sem faixa' : (tiers.find((tier) => tier.id === token)?.name ?? ''),
  )
  return names.filter(Boolean).join(', ') || 'Todos'
}
