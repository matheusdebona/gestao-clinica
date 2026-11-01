// @vitest-environment happy-dom
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SidebarNavItem from './SidebarNavItem.vue'

describe('SidebarNavItem shell tone', () => {
  it('uses theme-aware shell tokens (light glass / dark unchanged) instead of inverse classes', () => {
    const idle = mount(SidebarNavItem, { props: { label: 'Leads', tone: 'shell' } })
    expect(idle.classes()).toContain('text-[var(--sv-shell-text)]')
    expect(idle.classes()).toContain('hover:bg-[var(--sv-shell-bg-hover)]')
    expect(idle.classes().some((c) => c.startsWith('text-inverse'))).toBe(false)
    expect(idle.classes()).not.toContain('text-title')

    const active = mount(SidebarNavItem, { props: { label: 'Início', tone: 'shell', active: true } })
    expect(active.attributes('aria-current')).toBe('page')
    expect(active.classes()).toContain('bg-[var(--sv-shell-bg-active)]')
    expect(active.classes()).toContain('text-[var(--sv-shell-text-active)]')
    expect(active.classes()).not.toContain('bg-brand/15')
  })

  it('keeps the inverse tone classes untouched', () => {
    const w = mount(SidebarNavItem, { props: { label: 'Início', active: true } })
    expect(w.classes()).toContain('bg-brand/40')
    expect(w.classes()).toContain('text-inverse')
  })
})
