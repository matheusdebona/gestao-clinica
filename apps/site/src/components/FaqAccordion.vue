<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)
const openId = ref<string | null>('planilha')

const items = [
  {
    id: 'planilha',
    q: 'O HOF Pay substitui a planilha da clínica?',
    a: 'Sim, esse é o ponto. Estoque, protocolos, vendas, tratamentos e margem ficam no mesmo ritmo — sem aba paralela para “o que realmente foi usado”.',
  },
  {
    id: 'celular',
    q: 'Funciona no celular?',
    a: 'O app da clínica é mobile-first. Médico e secretária usam no dia a dia, não só no computador da recepção.',
  },
  {
    id: 'dados',
    q: 'Os dados da minha clínica se misturam com os de outra?',
    a: 'Não. Os dados de cada clínica ficam separados: produtos, clientes, agenda e números não se misturam.',
  },
  {
    id: 'estoque',
    q: 'A venda já baixa o estoque?',
    a: 'Não. A venda e o contrato acontecem primeiro. A baixa é no tratamento, quando o médico informa o que foi de fato aplicado.',
  },
  {
    id: 'preco',
    q: 'Quanto custa?',
    a: 'Ainda não há tabela pública. Não publicamos valores inventados. Crie a clínica para começar; a oferta comercial entra quando estiver pronta.',
  },
  {
    id: 'acesso',
    q: 'Como entro no sistema?',
    a: 'Pelo app em app.hofpay.com.br. Cadastro cria a clínica e o primeiro usuário admin. Quem já tem conta entra pelo login.',
  },
]

function toggle(id: string) {
  openId.value = openId.value === id ? null : id
}

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  if (reduceMotion) {
    gsap.set('.faq-intro, .faq-item', { autoAlpha: 1, y: 0 })
    return
  }

  gsap.from('.faq-intro', {
    autoAlpha: 0,
    y: 20,
    duration: 0.7,
    ease: 'power3.out',
    scrollTrigger: { trigger: '.faq-intro', start: 'top 82%' },
  })

  gsap.from('.faq-item', {
    autoAlpha: 0,
    y: 18,
    duration: 0.55,
    stagger: 0.07,
    ease: 'power2.out',
    scrollTrigger: { trigger: '.faq-list', start: 'top 80%' },
  })
})
</script>

<template>
  <section id="faq" ref="root" class="py-16 sm:py-24">
    <div class="site-wrap grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
      <div class="faq-intro max-w-md">
        <p class="eyebrow glass-chip glass-chip-brand text-brand">FAQ</p>
        <h2 class="section-title mt-4 text-title">Perguntas diretas.</h2>
        <p class="mt-4 text-[16px] leading-7 text-body">
          Sem jargão de SaaS. Se a dúvida for outra, crie a clínica e veja o produto — o cadastro já abre o app.
        </p>
      </div>

      <div class="faq-list grid gap-2">
        <article
          v-for="item in items"
          :key="item.id"
          class="faq-item overflow-hidden rounded-xl glass-regular"
        >
          <h3>
            <button
              type="button"
              class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left text-[15px] font-semibold tracking-[-0.02em] text-title sm:px-5"
              :aria-expanded="openId === item.id"
              @click="toggle(item.id)"
            >
              <span>{{ item.q }}</span>
              <ChevronDown
                class="size-4 shrink-0 text-muted transition-transform duration-200"
                :class="openId === item.id && 'rotate-180'"
                aria-hidden="true"
              />
            </button>
          </h3>
          <p
            v-show="openId === item.id"
            class="border-t border-border-divider px-4 pb-4 pt-3 text-[15px] leading-6 text-body sm:px-5"
          >
            {{ item.a }}
          </p>
        </article>
      </div>
    </div>
  </section>
</template>
