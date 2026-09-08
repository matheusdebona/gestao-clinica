<script setup lang="ts">
import { CalendarDays, ChartNoAxesCombined, Layers, Package, Users } from '@lucide/vue'
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)

const features = [
  {
    title: 'Estoque',
    copy: 'Lotes, validade e reposição no ritmo da clínica. A baixa só acontece quando o tratamento é de fato aplicado.',
    icon: Package,
  },
  {
    title: 'Protocolos',
    copy: 'Procedimentos montados com produtos, sessões e preços — custo, sugerido, mínimo e especial, sem improviso.',
    icon: Layers,
  },
  {
    title: 'Clientes',
    copy: 'Cadastro, origem e campanhas. A recepção encontra a pessoa certa sem perder o fio da conversa.',
    icon: Users,
  },
  {
    title: 'Agenda',
    copy: 'Sessões, retornos e o dia do médico em um olhar. Pensada para o celular, não só para o desktop.',
    icon: CalendarDays,
  },
  {
    title: 'Métricas',
    copy: 'Margem, comercial e operação. Números da clínica, sem planilha paralela e sem teatro de dashboard.',
    icon: ChartNoAxesCombined,
  },
]

useGsapIsland(root, ({ gsap, reduceMotion, isDesktop }) => {
  if (reduceMotion) {
    gsap.set('.feature-intro, .feature-card', { autoAlpha: 1, y: 0 })
    return
  }

  gsap.from('.feature-intro', {
    autoAlpha: 0,
    y: 28,
    duration: 0.8,
    ease: 'power3.out',
    scrollTrigger: { trigger: '.feature-intro', start: 'top 82%' },
  })

  gsap.from('.feature-card', {
    autoAlpha: 0,
    y: isDesktop ? 36 : 24,
    duration: 0.75,
    ease: 'power3.out',
    stagger: 0.1,
    scrollTrigger: { trigger: '.feature-grid', start: 'top 78%' },
  })
})
</script>

<template>
  <section id="produto" ref="root" class="relative py-20 sm:py-28">
    <div class="site-wrap">
      <div class="feature-intro max-w-2xl">
        <p class="eyebrow glass-chip glass-chip-brand text-brand">O produto</p>
        <h2 class="section-title mt-4 text-title">Cinco frentes. Um ritmo só.</h2>
        <p class="mt-4 max-w-xl text-[16px] leading-7 text-body">
          HOF Pay não é um amontoado de telas. É o percurso da clínica: do estoque na geladeira
          à sessão na poltrona, com a margem visível no fim do dia.
        </p>
      </div>

      <div class="feature-grid mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <article
          v-for="(feature, index) in features"
          :key="feature.title"
          class="feature-card glass-regular rounded-xl p-5"
          :class="index < 2 ? 'lg:col-span-3' : 'lg:col-span-2'"
        >
          <span class="grid size-10 place-items-center rounded-[12px] bg-brand text-inverse">
            <component :is="feature.icon" class="size-5" aria-hidden="true" />
          </span>
          <h3 class="mt-5 text-[20px] font-semibold tracking-[-0.03em] text-title">{{ feature.title }}</h3>
          <p class="mt-2 text-[15px] leading-6 text-body">{{ feature.copy }}</p>
        </article>
      </div>
    </div>
  </section>
</template>
