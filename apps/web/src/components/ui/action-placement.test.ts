// @vitest-environment happy-dom
import { mount } from '@vue/test-utils'
import { h } from 'vue'
import { describe, expect, it } from 'vitest'
import ActionBar from '@/components/ui/ActionBar.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import InfoTip from '@/components/ui/InfoTip.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

describe('ActionBar', () => {
  it('puts negatives in the left group and positives in the right group', () => {
    const w = mount(ActionBar, {
      slots: {
        negative: () => h(Button, { variant: 'ghost' }, () => 'Cancelar'),
        default: () => [h(Button, { variant: 'secondary' }, () => 'Editar'), h(Button, () => 'Salvar')],
      },
    })
    const groups = w.element.children
    expect(groups).toHaveLength(2)
    expect(groups[0]!.textContent).toBe('Cancelar')
    expect(groups[1]!.textContent).toBe('EditarSalvar')
    // mobile: stacked (primary on top via reverse) and full width
    expect(w.classes()).toContain('flex-col-reverse')
    expect(groups[1]!.className).toContain('flex-col-reverse')
    expect(groups[1]!.className).toContain('max-sm:[&_button]:w-full')
    expect(groups[1]!.className).toContain('sm:justify-end')
  })

  it('renders only the positive group without negatives', () => {
    const w = mount(ActionBar, { slots: { default: () => h(Button, () => 'Respondeu') } })
    expect(w.element.children).toHaveLength(1)
  })
})

describe('InfoTip pattern', () => {
  it('exposes the hint to screen readers via aria-describedby', () => {
    const w = mount(InfoTip, { props: { text: 'Texto de ajuda', label: 'Sobre: Taxa' } })
    const trigger = w.get('button')
    expect(trigger.attributes('aria-label')).toBe('Sobre: Taxa')
    const id = trigger.attributes('aria-describedby')!
    expect(w.get(`#${CSS.escape(id)}`).text()).toBe('Texto de ajuda')
  })

  it('FormField moves long hints behind the info icon and keeps short ones inline', () => {
    const long = 'Se a operadora antecipa os recebíveis do crédito, informe a taxa ao mês e a regra.'
    const a = mount(FormField, { props: { label: 'Taxa', hint: long } })
    expect(a.findComponent(InfoTip).exists()).toBe(true)
    expect(a.find('p').exists()).toBe(false)
    const b = mount(FormField, { props: { label: 'Taxa', hint: 'Opcional.' } })
    expect(b.findComponent(InfoTip).exists()).toBe(false)
    expect(b.text()).toContain('Opcional.')
    const c = mount(FormField, { props: { label: 'Taxa', hint: long, error: 'Obrigatório' } })
    expect(c.text()).toContain('Obrigatório')
  })

  it('PageHeader keeps short subtitles inline and moves long ones into the tip', () => {
    const shortH = mount(PageHeader, {
      props: { title: 'Despesas', description: 'Custos por mês.' },
      global: { stubs: { RouterLink: true } },
    })
    expect(shortH.text()).toContain('Custos por mês.')
    expect(shortH.findComponent(InfoTip).exists()).toBe(false)
    const longH = mount(PageHeader, {
      props: {
        title: 'Comissões',
        description:
          'Regras para vendedores (por venda) e profissionais (por sessão realizada). Os lançamentos são gerados ao confirmar vendas.',
      },
      global: { stubs: { RouterLink: true } },
    })
    expect(longH.findComponent(InfoTip).exists()).toBe(true)
  })
})
