import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import {
  applyResolvedTheme,
  persistPreference,
  readStoredPreference,
  resolveTheme,
  type ThemePreference,
} from '@/lib/theme'

function systemPrefersDark(): boolean {
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export const useThemeStore = defineStore('theme', () => {
  const preference = ref<ThemePreference>(
    typeof window === 'undefined' ? 'system' : readStoredPreference(),
  )
  const systemDark = ref(typeof window === 'undefined' ? false : systemPrefersDark())

  const resolved = computed(() => resolveTheme(preference.value, systemDark.value))

  function apply() {
    if (typeof document === 'undefined') {
      return
    }
    applyResolvedTheme(resolved.value)
  }

  function setPreference(next: ThemePreference) {
    preference.value = next
    persistPreference(next)
  }

  watch(resolved, apply, { immediate: true })

  if (typeof window !== 'undefined') {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
      systemDark.value = event.matches
    })
  }

  return {
    preference,
    systemDark,
    resolved,
    setPreference,
  }
})
