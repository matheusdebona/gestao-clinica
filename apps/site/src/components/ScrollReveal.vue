<script setup lang="ts">
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)

useGsapIsland(root, ({ gsap, reduceMotion, isDesktop }) => {
  if (reduceMotion) return

  const items = root.value?.querySelectorAll<HTMLElement>('[data-reveal]') ?? []
  const groups = root.value?.querySelectorAll<HTMLElement>('[data-reveal-stagger]') ?? []

  items.forEach((el) => {
    gsap.from(el, {
      y: isDesktop ? 12 : 8,
      duration: 0.5,
      ease: 'power2.out',
      scrollTrigger: { trigger: el, start: 'top 90%', once: true },
    })
  })

  groups.forEach((group) => {
    gsap.from(group.querySelectorAll('[data-reveal-item]'), {
      y: 8,
      duration: 0.4,
      ease: 'power2.out',
      stagger: 0.04,
      scrollTrigger: { trigger: group, start: 'top 90%', once: true },
    })
  })
})
</script>

<template>
  <div ref="root">
    <slot />
  </div>
</template>
