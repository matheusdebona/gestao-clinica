<script setup lang="ts">
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'
import { APP_REGISTER_URL } from '../lib/urls'

const root = ref<HTMLElement | null>(null)

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  if (reduceMotion) {
    gsap.set('.access-panel', { autoAlpha: 1, y: 0 })
    return
  }

  gsap.from('.access-panel', {
    autoAlpha: 0,
    y: 32,
    duration: 0.85,
    ease: 'power3.out',
    scrollTrigger: { trigger: root.value, start: 'top 78%' },
  })
})
</script>

<template>
  <section id="acesso" ref="root" class="py-10 sm:py-16">
    <div class="site-wrap">
      <div class="access-panel relative overflow-hidden rounded-chrome hero-mesh px-6 py-12 text-inverse sm:px-12 sm:py-16">
        <div class="pointer-events-none absolute -right-10 -top-10 size-56 rounded-full bg-brand/40 blur-3xl" />
        <div class="relative max-w-2xl">
          <p class="eyebrow glass-dark text-inverse/80">Acesso</p>
          <h2 class="section-title mt-4 text-inverse">Comece pela clínica. Planos comerciais, em breve.</h2>
          <p class="mt-4 max-w-xl text-[16px] leading-7 text-inverse/75">
            Ainda não publicamos tabela de preços. Não inventamos valores: quando a comercialização
            estiver pronta, ela aparece aqui. Enquanto isso, você já pode criar a clínica e usar o produto.
          </p>
          <div class="mt-8">
            <a class="site-cta-primary" :href="APP_REGISTER_URL">Criar clínica agora</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
