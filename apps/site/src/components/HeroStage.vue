<script setup lang="ts">
import { CalendarDays, ChartNoAxesCombined, Package } from '@lucide/vue'
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'
import { APP_LOGIN_URL, APP_REGISTER_URL } from '../lib/urls'

const root = ref<HTMLElement | null>(null)

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  if (reduceMotion) {
    gsap.set(['.hero-copy > *', '.hero-stage', '.hero-orb'], { autoAlpha: 1, y: 0, x: 0, scale: 1, rotation: 0 })
    return
  }

  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })
  tl.from('.hero-copy > *', { autoAlpha: 0, y: 28, duration: 0.9, stagger: 0.08 })
    .from('.hero-stage', { autoAlpha: 0, y: 36, scale: 0.96, duration: 1 }, '-=0.55')
    .from('.hero-orb', { autoAlpha: 0, scale: 0.7, duration: 1.2, stagger: 0.12 }, '-=0.9')

  gsap.to('.hero-orb-a', {
    y: 18,
    x: 10,
    duration: 6.5,
    yoyo: true,
    repeat: -1,
    ease: 'sine.inOut',
  })
  gsap.to('.hero-orb-b', {
    y: -16,
    x: -12,
    duration: 7.5,
    yoyo: true,
    repeat: -1,
    ease: 'sine.inOut',
  })
  gsap.to('.hero-stage', {
    y: -8,
    duration: 5.5,
    yoyo: true,
    repeat: -1,
    ease: 'sine.inOut',
  })
})
</script>

<template>
  <section ref="root" id="topo" class="hero-mesh relative overflow-hidden text-inverse">
    <div class="hero-orb hero-orb-a pointer-events-none absolute -left-16 top-24 size-[22rem] rounded-full bg-brand/35 blur-3xl" />
    <div class="hero-orb hero-orb-b pointer-events-none absolute -right-10 bottom-10 size-[18rem] rounded-full bg-brand/25 blur-3xl" />

    <div class="site-wrap relative grid min-h-dvh items-center gap-12 pb-20 pt-28 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:pb-24 lg:pt-32">
      <div class="hero-copy max-w-xl">
        <p class="eyebrow glass-dark text-inverse/80">Para o dia a dia da clínica</p>
        <h1 class="display-title mt-5 text-inverse">
          Gestão da clínica,<br class="hidden sm:block" />
          com calma.
        </h1>
        <p class="mt-5 max-w-[34rem] text-[17px] leading-7 text-inverse/80">
          HOF Pay é a plataforma de gestão para clínicas: estoque, protocolos, clientes, agenda e métricas.
        </p>
        <p class="mt-3 max-w-[34rem] text-[16px] leading-7 text-inverse/70">
          Pensado para o médico e a secretária, no celular e no consultório.
        </p>
        <div class="mt-8 flex flex-wrap items-center gap-3">
          <a class="site-cta-primary" :href="APP_REGISTER_URL">Criar clínica</a>
          <a class="site-cta-secondary glass-dark text-inverse" :href="APP_LOGIN_URL">Já tenho acesso</a>
        </div>
        <p class="mt-5 text-[13px] text-inverse/55">
          Sem planilha paralela. A baixa de estoque só acontece quando o tratamento acontece.
        </p>
      </div>

      <div class="hero-stage relative">
        <div class="glass-dark rounded-chrome p-4 shadow-glass-dark sm:p-5">
          <div class="mb-4 flex items-center justify-between gap-3">
            <div>
              <p class="text-[12px] font-medium uppercase tracking-[0.08em] text-inverse/50">Hoje na clínica</p>
              <p class="mt-1 text-[17px] font-semibold tracking-[-0.03em] text-inverse">Agenda e operação</p>
            </div>
            <span class="glass-chip eyebrow text-inverse/80">Ao vivo</span>
          </div>

          <div class="grid gap-3 sm:grid-cols-3">
            <div class="glass-clear rounded-card px-3 py-3">
              <Package class="size-4 text-inverse/70" aria-hidden="true" />
              <p class="mt-3 text-[12px] text-inverse/55">Estoque</p>
              <p class="text-[20px] font-semibold tracking-[-0.03em] text-inverse">estável</p>
            </div>
            <div class="glass-clear rounded-card px-3 py-3">
              <CalendarDays class="size-4 text-inverse/70" aria-hidden="true" />
              <p class="mt-3 text-[12px] text-inverse/55">Agenda</p>
              <p class="text-[20px] font-semibold tracking-[-0.03em] text-inverse">12 sessões</p>
            </div>
            <div class="glass-clear rounded-card px-3 py-3">
              <ChartNoAxesCombined class="size-4 text-inverse/70" aria-hidden="true" />
              <p class="mt-3 text-[12px] text-inverse/55">Margem</p>
              <p class="text-[20px] font-semibold tracking-[-0.03em] text-inverse">visível</p>
            </div>
          </div>

          <ul class="mt-4 grid gap-2">
            <li class="glass-clear flex items-center justify-between rounded-card px-3 py-2.5">
              <span class="text-[14px] text-inverse">Protocolo Harmonização</span>
              <span class="text-[12px] text-inverse/55">14:00</span>
            </li>
            <li class="glass-clear flex items-center justify-between rounded-card px-3 py-2.5">
              <span class="text-[14px] text-inverse">Reposição de toxina</span>
              <span class="text-[12px] text-inverse/55">estoque</span>
            </li>
            <li class="glass-clear flex items-center justify-between rounded-card px-3 py-2.5">
              <span class="text-[14px] text-inverse">Sessão 2 de 4</span>
              <span class="text-[12px] text-inverse/55">tratamento</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>
