<script setup lang="ts">
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)

const labels = [
  'Estética',
  'Dermatologia',
  'Harmonização',
  'Odontologia',
  'Procedimentos injetáveis',
  'Medicina integrativa',
  'Clínicas boutique',
  'Multiunidade',
]

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  if (reduceMotion) {
    gsap.set('.proof-intro, .proof-chip, .proof-track', { autoAlpha: 1, y: 0, x: 0 })
    return
  }

  gsap.from('.proof-intro', {
    autoAlpha: 0,
    y: 20,
    duration: 0.7,
    ease: 'power3.out',
    scrollTrigger: { trigger: root.value, start: 'top 80%' },
  })

  gsap.from('.proof-chip', {
    autoAlpha: 0,
    y: 16,
    duration: 0.55,
    stagger: 0.06,
    ease: 'power2.out',
    scrollTrigger: { trigger: '.proof-track', start: 'top 85%' },
  })
})
</script>

<template>
  <section id="clinicas" ref="root" class="py-16 sm:py-20">
    <div class="site-wrap">
      <div class="proof-intro flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
        <div class="max-w-xl">
          <p class="eyebrow glass-chip text-muted">Para clínicas</p>
          <h2 class="section-title mt-4 text-title">Feito para quem aplica, não só para quem administra.</h2>
        </div>
        <p class="max-w-sm text-[15px] leading-6 text-body">
          Cada clínica é um tenant. Seus produtos, clientes e números não se misturam com os de outra.
        </p>
      </div>

      <div class="proof-track mt-8 flex flex-wrap gap-2">
        <span
          v-for="label in labels"
          :key="label"
          class="proof-chip glass-chip glass-chip-brand rounded-full px-3.5 py-2 text-[13px] font-medium text-brand"
        >
          {{ label }}
        </span>
      </div>
    </div>
  </section>
</template>
