<script setup lang="ts">
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)

useGsapIsland(root, ({ gsap, reduceMotion, isDesktop }) => {
  const items = root.value?.querySelectorAll<HTMLElement>('[data-reveal]') ?? []
  const groups = root.value?.querySelectorAll<HTMLElement>('[data-reveal-stagger]') ?? []

  if (reduceMotion) {
    gsap.set(items, { autoAlpha: 1, y: 0, scaleY: 1 })
    groups.forEach((group) => {
      gsap.set(group.querySelectorAll('[data-reveal-item]'), { autoAlpha: 1, y: 0 })
    })
    return
  }

  items.forEach((el) => {
    gsap.from(el, {
      autoAlpha: 0,
      y: isDesktop ? 28 : 20,
      duration: 0.75,
      ease: 'power3.out',
      scrollTrigger: { trigger: el, start: 'top 82%' },
    })
  })

  groups.forEach((group) => {
    gsap.from(group.querySelectorAll('[data-reveal-item]'), {
      autoAlpha: 0,
      y: 22,
      duration: 0.65,
      ease: 'power3.out',
      stagger: 0.08,
      scrollTrigger: { trigger: group, start: 'top 80%' },
    })
  })
})
</script>

<template>
  <div ref="root">
    <slot />
  </div>
</template>
