import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { listFollowUpTiers } from '@/features/leads/api'

export const FOLLOW_UP_TIERS_KEY = ['lead-follow-up-tiers'] as const

export function useFollowUpTiers() {
  const query = useQuery({
    queryKey: FOLLOW_UP_TIERS_KEY,
    queryFn: listFollowUpTiers,
    staleTime: 5 * 60_000,
  })
  const tiers = computed(() => [...(query.data.value ?? [])].sort((a, b) => a.days - b.days))
  return { ...query, tiers }
}
