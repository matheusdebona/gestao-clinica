<script setup lang="ts">
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)

const steps = [
  {
    n: '01',
    title: 'Cadastrar produtos',
    copy: 'O que entra na clínica fica registrado: lote, validade, custo real.',
  },
  {
    n: '02',
    title: 'Montar protocolos',
    copy: 'O procedimento deixa de ser “a gosto”. Vira um serviço com composição e preço.',
  },
  {
    n: '03',
    title: 'Vender sem baixar estoque',
    copy: 'A venda e o contrato acontecem. O frasco ainda não saiu da geladeira.',
  },
  {
    n: '04',
    title: 'Tratar e baixar o que foi usado',
    copy: 'Na sessão, o médico informa o que foi aplicado. Aí sim o estoque e a margem fecham.',
  },
]

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  if (reduceMotion) {
    gsap.set('.rhythm-intro, .rhythm-step, .rhythm-line', { autoAlpha: 1, y: 0, scaleY: 1 })
    return
  }

  gsap.from('.rhythm-intro', {
    autoAlpha: 0,
    y: 24,
    duration: 0.75,
    ease: 'power3.out',
    scrollTrigger: { trigger: '.rhythm-intro', start: 'top 82%' },
  })

  gsap.from('.rhythm-step', {
    autoAlpha: 0,
    y: 32,
    duration: 0.7,
    ease: 'power3.out',
    stagger: 0.12,
    scrollTrigger: { trigger: '.rhythm-list', start: 'top 76%' },
  })

  gsap.from('.rhythm-line', {
    scaleY: 0,
    transformOrigin: 'top',
    duration: 1.1,
    ease: 'power2.out',
    scrollTrigger: { trigger: '.rhythm-list', start: 'top 76%' },
  })
})
</script>

<template>
  <section ref="root" class="pb-8 sm:pb-12">
    <div class="site-wrap">
      <div class="glass-regular overflow-hidden rounded-chrome px-5 py-10 sm:px-10 sm:py-14">
        <div class="rhythm-intro max-w-2xl">
          <p class="eyebrow glass-chip glass-chip-brand text-brand">O ritmo</p>
          <h2 class="section-title mt-4 text-title">Vender não é baixar estoque.</h2>
          <p class="mt-4 max-w-xl text-[16px] leading-7 text-body">
            Na clínica, o produto só sai quando a sessão acontece. HOF Pay respeita essa ordem —
            e é isso que deixa a margem verdadeira.
          </p>
        </div>

        <div class="rhythm-list relative mt-10 grid gap-4">
          <div class="rhythm-line pointer-events-none absolute top-3 bottom-3 left-[1.15rem] hidden w-px bg-brand/25 sm:block" />
          <article
            v-for="step in steps"
            :key="step.n"
            class="rhythm-step relative grid gap-2 rounded-xl glass-clear px-4 py-4 sm:grid-cols-[auto_1fr] sm:items-start sm:gap-5 sm:px-5"
          >
            <span class="grid size-8 place-items-center rounded-full bg-brand text-[12px] font-semibold text-inverse">
              {{ step.n }}
            </span>
            <div>
              <h3 class="text-[17px] font-semibold tracking-[-0.02em] text-title">{{ step.title }}</h3>
              <p class="mt-1 text-[15px] leading-6 text-body">{{ step.copy }}</p>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>
