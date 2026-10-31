import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { getClinicSettings } from '@/features/settings/api'
import { useAuthStore } from '@/stores/auth'

export const CLINIC_SETTINGS_KEY = ['clinic-settings'] as const

/** Clinic preferences (WhatsApp templates…). Cached; readable by leads/agenda users. */
export function useClinicSettings() {
  const auth = useAuthStore()
  const enabled = computed(
    () =>
      auth.can('leads.view') ||
      auth.can('appointments.view') ||
      auth.can('treatments.view') ||
      auth.can('clinics.branding') ||
      auth.can('clinics.manage'),
  )

  return useQuery({
    queryKey: CLINIC_SETTINGS_KEY,
    queryFn: getClinicSettings,
    enabled,
    staleTime: 5 * 60 * 1000,
  })
}
