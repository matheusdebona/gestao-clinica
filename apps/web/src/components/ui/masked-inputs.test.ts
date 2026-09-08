// @vitest-environment happy-dom
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import { defineComponent, nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import PhoneInput from '@/components/ui/PhoneInput.vue'

const defineFieldAttrs = {
  value: '11987654321',
  onInput: () => undefined,
  onChange: () => undefined,
  'onUpdate:modelValue': () => undefined,
  'onUpdate:model-value': () => undefined,
  name: 'whatsapp',
}

async function typeInto(wrapper: ReturnType<typeof mount>, text: string) {
  const input = wrapper.get('input')
  const el = input.element as HTMLInputElement
  for (const char of text) {
    el.value = `${el.value}${char}`
    await input.trigger('input')
    await nextTick()
  }
}

describe('Input', () => {
  it('keeps the controlled value when fallthrough attrs include raw value', () => {
    const wrapper = mount(Input, {
      props: { modelValue: '(11) 98765-4321' },
      attrs: { value: '11987654321' },
    })

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('(11) 98765-4321')
  })
})

describe('PhoneInput', () => {
  it('shows the BR mask even when defineField-like attrs pass a raw value', async () => {
    const wrapper = mount(PhoneInput, {
      props: { modelValue: '11987654321' },
      attrs: defineFieldAttrs,
    })

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('(11) 98765-4321')
  })

  it('formats while typing when wired like ClientForm (v-model + defineField attrs)', async () => {
    const Host = defineComponent({
      components: { PhoneInput },
      setup() {
        const { defineField } = useForm({
          initialValues: { whatsapp: '' },
        })
        const [whatsapp, whatsappAttrs] = defineField('whatsapp')
        return { whatsapp, whatsappAttrs }
      },
      template:
        '<PhoneInput id="client-whatsapp" v-model="whatsapp" v-bind="whatsappAttrs" />',
    })

    const wrapper = mount(Host)
    await typeInto(wrapper, '11987654321')

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('(11) 98765-4321')
    expect(wrapper.findComponent(PhoneInput).props('modelValue')).toBe('11987654321')
  })
})

describe('MoneyInput', () => {
  it('shows the cents mask even when defineField-like attrs pass a raw value', () => {
    const wrapper = mount(MoneyInput, {
      props: { modelValue: '1280.56' },
      attrs: {
        ...defineFieldAttrs,
        value: '128056',
        name: 'initial_consultation_amount',
      },
    })

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('1.280,56')
  })

  it('formats cents while typing with v-model + defineField attrs', async () => {
    const Host = defineComponent({
      components: { MoneyInput },
      setup() {
        const { defineField } = useForm({
          initialValues: { initial_consultation_amount: '' },
        })
        const [consultation, consultationAttrs] = defineField('initial_consultation_amount')
        return { consultation, consultationAttrs }
      },
      template:
        '<MoneyInput id="client-consultation" v-model="consultation" v-bind="consultationAttrs" />',
    })

    const wrapper = mount(Host)
    await typeInto(wrapper, '128056')

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('1.280,56')
    expect(wrapper.findComponent(MoneyInput).props('modelValue')).toBe('1280.56')
  })
})
