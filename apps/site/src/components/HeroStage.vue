<script setup lang="ts">
import { CalendarDays, ChartNoAxesCombined, Package } from '@lucide/vue'
import { ref } from 'vue'
import {
  CTA_LOGIN_LABEL,
  CTA_REGISTER_STRONG_LABEL,
  SITE_ONE_LINER,
} from '../content/site'
import { useGsapIsland } from '../lib/use-gsap-island'
import { APP_LOGIN_URL, APP_REGISTER_URL } from '../lib/urls'

const root = ref<HTMLElement | null>(null)

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  if (reduceMotion) {
    gsap.set(['.hero-copy > *', '.hero-stage', '.hero-orb'], {
      autoAlpha: 1,
      y: 0,
      x: 0,
      scale: 1,
    })
    return
  }

  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })
  tl.from('.hero-copy > *', { autoAlpha: 0, y: 22, duration: 0.8, stagger: 0.07 }).from(
    '.hero-stage',
    { autoAlpha: 0, y: 28, scale: 0.98, duration: 0.9 },
    '-=0.5',
  )

  gsap.to('.hero-orb-a', {
    y: 14,
    x: 8,
    duration: 8,
    yoyo: true,
    repeat: -1,
    ease: 'sine.inOut',
  })
  gsap.to('.hero-orb-b', {
    y: -12,
    x: -8,
    duration: 9,
    yoyo: true,
    repeat: -1,
    ease: 'sine.inOut',
  })
})
</script>

<template>
  <section ref="root" id="topo" class="hero-mesh relative overflow-hidden text-inverse">
    <div
      class="hero-orb hero-orb-a pointer-events-none absolute -left-16 top-24 size-[22rem] rounded-full bg-brand/35 blur-3xl will-change-transform"
    />
    <div
      class="hero-orb hero-orb-b pointer-events-none absolute -right-10 bottom-10 size-[18rem] rounded-full bg-brand/25 blur-3xl will-change-transform"
    />

    <div
      class="site-wrap relative grid min-h-dvh items-center gap-12 pb-24 pt-28 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:pb-28 lg:pt-32"
    >
      <div class="hero-copy max-w-xl">
        <p class="eyebrow glass-dark text-inverse/80">Para o dia a dia da clínica</p>
        <h1 class="display-title mt-5 text-inverse">
          Estoque, pacientes e agenda
          <span class="block">no ritmo da sessão.</span>
        </h1>
        <p class="mt-5 max-w-[36rem] text-[17px] leading-7 text-inverse/80">
          {{ SITE_ONE_LINER }}
        </p>
        <p class="mt-3 max-w-[36rem] text-[16px] leading-7 text-inverse/70">
          Orçamento aceito vira venda. O estoque só baixa no tratamento. Pensado para
          profissionais de harmonização e a equipe.
        </p>
        <div class="mt-8 flex flex-wrap items-center gap-3">
          <a class="site-cta-primary" :href="APP_REGISTER_URL" data-section-cta>
            {{ CTA_REGISTER_STRONG_LABEL }}
          </a>
          <a class="site-cta-secondary site-cta-on-dark" :href="APP_LOGIN_URL">
            {{ CTA_LOGIN_LABEL }}
          </a>
        </div>
      </div>

      <div class="hero-stage relative">
        <div class="overflow-hidden rounded-chrome glass-dark shadow-glass-dark">
          <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-2.5">
            <span class="size-2 rounded-full bg-inverse/25" />
            <span class="size-2 rounded-full bg-inverse/25" />
            <span class="size-2 rounded-full bg-inverse/25" />
            <span class="ml-2 text-[12px] text-inverse/45">app.hofpay.com.br</span>
          </div>

          <div class="p-4 sm:p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <p class="text-[12px] font-medium uppercase tracking-[0.08em] text-inverse/50">
                  Um dia na clínica
                </p>
                <p class="mt-1 text-[17px] font-semibold tracking-[-0.03em] text-inverse">
                  Agenda, estoque e sessão
                </p>
              </div>
              <span class="glass-chip eyebrow text-inverse/80">Exemplo</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
              <div class="glass-clear rounded-card px-3 py-3">
                <Package class="size-4 text-inverse/70" aria-hidden="true" />
                <p class="mt-3 text-[12px] text-inverse/55">Estoque</p>
                <p class="text-[18px] font-semibold tracking-[-0.03em] text-inverse">na sessão</p>
              </div>
              <div class="glass-clear rounded-card px-3 py-3">
                <CalendarDays class="size-4 text-inverse/70" aria-hidden="true" />
                <p class="mt-3 text-[12px] text-inverse/55">Agenda</p>
                <p class="text-[18px] font-semibold tracking-[-0.03em] text-inverse">3 sessões</p>
              </div>
              <div class="glass-clear rounded-card px-3 py-3">
                <ChartNoAxesCombined class="size-4 text-inverse/70" aria-hidden="true" />
                <p class="mt-3 text-[12px] text-inverse/55">Margem</p>
                <p class="text-[18px] font-semibold tracking-[-0.03em] text-inverse">depois da baixa</p>
              </div>
            </div>

            <ul class="mt-4 grid gap-2">
              <li class="glass-clear flex items-center justify-between rounded-card px-3 py-2.5">
                <span class="text-[14px] text-inverse">Protocolo Harmonização</span>
                <span class="text-[12px] text-inverse/55">14:00</span>
              </li>
              <li class="glass-clear flex items-center justify-between rounded-card px-3 py-2.5">
                <span class="text-[14px] text-inverse">Paciente · sessão 2 de 4</span>
                <span class="text-[12px] text-inverse/55">tratamento</span>
              </li>
              <li class="glass-clear flex items-center justify-between rounded-card px-3 py-2.5">
                <span class="text-[14px] text-inverse">Orçamento aceito → venda</span>
                <span class="text-[12px] text-inverse/55">comercial</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
