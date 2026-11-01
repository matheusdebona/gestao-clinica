<script setup lang="ts">
import { ref } from 'vue'
import { useGsapIsland } from '../lib/use-gsap-island'

const root = ref<HTMLElement | null>(null)

useGsapIsland(root, ({ gsap, reduceMotion }) => {
  const sheet = root.value?.querySelector<HTMLElement>('.hero-sheet')
  const phone = root.value?.querySelector<HTMLElement>('.hero-phone')
  const caption = root.value?.querySelector<HTMLElement>('.hero-stage-caption')

  if (reduceMotion) {
    gsap.set(['.hero-copy > *', sheet, phone, caption], { autoAlpha: 1, y: 0, x: 0, scale: 1 })
    if (sheet) gsap.set(sheet, { autoAlpha: 0 })
    if (phone) gsap.set(phone, { autoAlpha: 1 })
    return
  }

  gsap.set(phone, { autoAlpha: 0, scale: 0.94, y: 28 })
  gsap.set(sheet, { autoAlpha: 1, y: 0, rotate: -1.4 })
  gsap.set(caption, { autoAlpha: 0 })

  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })
  tl.from('.hero-copy > *', { autoAlpha: 0, y: 18, duration: 0.7, stagger: 0.06 })
    .from(sheet, { autoAlpha: 0, y: 22, duration: 0.7 }, '-=0.45')
    .to(
      sheet,
      {
        autoAlpha: 0,
        scale: 0.86,
        y: 36,
        rotate: 3,
        filter: 'blur(6px)',
        duration: 0.85,
        ease: 'power2.in',
      },
      '+=0.55',
    )
    .to(
      phone,
      { autoAlpha: 1, scale: 1, y: 0, duration: 0.85, ease: 'power3.out' },
      '-=0.42',
    )
    .to(caption, { autoAlpha: 1, duration: 0.4 }, '-=0.2')
})
</script>

<template>
  <div ref="root">
    <slot />
  </div>
</template>
