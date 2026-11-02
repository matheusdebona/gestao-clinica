// @vitest-environment happy-dom
import { mount, RouterLinkStub } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import ShellNavEntries from './ShellNavEntries.vue'
import {
  buildVisibleNav,
  initialOpenGroups,
  toggleExclusiveGroup,
  type OpenGroups,
} from '@/lib/shell-nav'

const nav = buildVisibleNav([], 'payment-methods')

function mountMenu(compact = false) {
  const Host = defineComponent({
    setup() {
      const open = ref<OpenGroups>(initialOpenGroups(nav, '/clients', null))
      return () =>
        h(ShellNavEntries, {
          entries: nav,
          openGroups: open.value,
          currentPath: '/clients',
          compact,
          onToggle: (id: string) => {
            open.value = toggleExclusiveGroup(open.value, id)
          },
        })
    },
  })
  return mount(Host, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

function expanded(wrapper: ReturnType<typeof mountMenu>) {
  return wrapper
    .findAll('button[aria-expanded]')
    .filter((button) => button.attributes('aria-expanded') === 'true')
    .map((button) => button.text())
}

function groupButton(wrapper: ReturnType<typeof mountMenu>, label: string) {
  const button = wrapper
    .findAll('button[aria-expanded]')
    .find((candidate) => candidate.text().includes(label) || candidate.attributes('title') === label)
  if (!button) throw new Error(`group ${label} not found`)
  return button
}

describe('ShellNavEntries exclusive accordion', () => {
  it('keeps only one group open at a time', async () => {
    const wrapper = mountMenu()
    expect(expanded(wrapper)).toHaveLength(1)
    expect(expanded(wrapper)[0]).toContain('Atendimento')

    await groupButton(wrapper, 'Comercial').trigger('click')
    expect(expanded(wrapper)).toHaveLength(1)
    expect(expanded(wrapper)[0]).toContain('Comercial')

    await groupButton(wrapper, 'Gestão').trigger('click')
    expect(expanded(wrapper)).toHaveLength(1)
    expect(expanded(wrapper)[0]).toContain('Gestão')

    await groupButton(wrapper, 'Gestão').trigger('click')
    expect(expanded(wrapper)).toHaveLength(0)
  })

  it('stays exclusive in the compact (icons only) menu', async () => {
    const wrapper = mountMenu(true)
    await groupButton(wrapper, 'Financeiro').trigger('click')
    const open = wrapper
      .findAll('button[aria-expanded="true"]')
      .map((button) => button.attributes('title'))
    expect(open).toEqual(['Financeiro'])
  })
})
