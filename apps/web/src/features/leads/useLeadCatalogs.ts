import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { listClientOrigins } from '@/features/clients/api'
import { listLeadCreators, listLeadStatuses } from '@/features/leads/api'
import { useAuthStore } from '@/stores/auth'

/** Statuses, origins (same list as patients) and clinic users for lead screens. */
export function useLeadCatalogs() {
  const auth = useAuthStore()
  const enabled = computed(() => auth.can('leads.view'))

  const statusesQuery = useQuery({
    queryKey: ['lead-statuses', 'all'],
    queryFn: () => listLeadStatuses(),
    enabled,
  })

  const originsQuery = useQuery({
    queryKey: ['client-origins', 'leads-catalog'],
    queryFn: () => listClientOrigins({ page: 1 }),
    enabled,
  })

  const creatorsQuery = useQuery({
    queryKey: ['leads', 'creators'],
    queryFn: listLeadCreators,
    enabled,
  })

  const statuses = computed(() => statusesQuery.data.value ?? [])
  const activeStatuses = computed(() => statuses.value.filter((status) => status.is_active))
  const origins = computed(() => originsQuery.data.value?.data ?? [])
  const activeOrigins = computed(() => origins.value.filter((origin) => origin.is_active))
  const creators = computed(() => creatorsQuery.data.value ?? [])

  return {
    statusesQuery,
    originsQuery,
    creatorsQuery,
    statuses,
    activeStatuses,
    origins,
    activeOrigins,
    creators,
  }
}
