// @vitest-environment happy-dom
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SidebarNavGroup from './SidebarNavGroup.vue'
import SidebarNavItem from './SidebarNavItem.vue'

describe('SidebarNavGroup', () => {
  it('exposes expanded state and keeps children out of the tab order when closed', async () => {
    const wrapper = mount(SidebarNavGroup, {
      props: { label: 'Atendimento', open: false },
      slots: {
        default: '<a href="/appointments">Agenda</a>',
      },
    })

    const trigger = wrapper.get('button')
    expect(trigger.attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('a').exists()).toBe(false)

    await wrapper.setProps({ open: true })
    expect(wrapper.get('button').attributes('aria-expanded')).toBe('true')
    expect(wrapper.get('a').text()).toBe('Agenda')
  })

  it('toggles from the keyboard and Escape while open', async () => {
    const wrapper = mount(SidebarNavGroup, {
      props: { label: 'Comercial', open: true },
    })

    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)

    await wrapper.trigger('keydown', { key: 'Escape' })
    expect(wrapper.emitted('toggle')).toHaveLength(2)
  })
})

describe('SidebarNavItem', () => {
  it('marks the active page and hides the label in compact mode', () => {
    const wrapper = mount(SidebarNavItem, {
      props: { label: 'Pacientes', active: true, compact: true },
    })

    const root = wrapper.get('span')
    expect(root.attributes('aria-current')).toBe('page')
    expect(root.attributes('title')).toBe('Pacientes')
    expect(wrapper.get('.sr-only').text()).toBe('Pacientes')
  })
})
