import { describe, expect, it } from 'vitest'
import { cycleThemePreference, isThemePreference, resolveTheme } from './theme'

describe('theme', () => {
  it('resolves system to dark when the OS is dark', () => {
    expect(resolveTheme('system', true)).toBe('dark')
  })

  it('resolves system to light when the OS is light', () => {
    expect(resolveTheme('system', false)).toBe('light')
  })

  it('honors an explicit preference over the OS', () => {
    expect(resolveTheme('light', true)).toBe('light')
    expect(resolveTheme('dark', false)).toBe('dark')
  })

  it('cycles system → light → dark → system', () => {
    expect(cycleThemePreference('system')).toBe('light')
    expect(cycleThemePreference('light')).toBe('dark')
    expect(cycleThemePreference('dark')).toBe('system')
  })

  it('accepts only known preference strings', () => {
    expect(isThemePreference('system')).toBe(true)
    expect(isThemePreference('light')).toBe(true)
    expect(isThemePreference('dark')).toBe(true)
    expect(isThemePreference('auto')).toBe(false)
    expect(isThemePreference(null)).toBe(false)
  })
})
