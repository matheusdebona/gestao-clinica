<script setup lang="ts">
import { Menu, X } from '@lucide/vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { CTA_LOGIN_SHORT_LABEL, CTA_REGISTER_LABEL } from '../content/site'
import { APP_LOGIN_URL, APP_NAME, APP_REGISTER_URL } from '../lib/urls'
import BrandMark from './BrandMark.vue'

const nav = [
  { href: '#lucro', label: 'Fechamento' },
  { href: '#funil', label: 'Leads' },
  { href: '#agenda', label: 'Agenda' },
  { href: '#produto', label: 'O sistema' },
  { href: '#faq', label: 'Perguntas' },
] as const

const open = ref(false)
const condensed = ref(false)
// Hidden at the very top so the hero gets the full first screen; slides in after a short scroll.
const revealed = ref(false)
const REVEAL_AT = 64
const heroInView = ref(true)
const sectionCtaInView = ref(true)
const footerNear = ref(false)

const showSticky = computed(
  () =>
    condensed.value &&
    !heroInView.value &&
    !open.value &&
    !sectionCtaInView.value &&
    !footerNear.value,
)
const onHero = computed(() => !condensed.value)

let ctaIo: IntersectionObserver | null = null
let footerIo: IntersectionObserver | null = null

function updateHeroLock() {
  const stage =
    document.querySelector<HTMLElement>('[data-hero-stage]') ??
    document.querySelector<HTMLElement>('.hero-stage') ??
    document.getElementById('topo')
  if (!stage) {
    heroInView.value = true
    return
  }
  const rect = stage.getBoundingClientRect()
  const stickyGuard = 120
  heroInView.value = rect.bottom > stickyGuard
}

function onScroll() {
  condensed.value = window.scrollY > 24
  revealed.value = window.scrollY > REVEAL_AT
  if (!revealed.value) open.value = false
  updateHeroLock()
}

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  onScroll()
  requestAnimationFrame(updateHeroLock)
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', updateHeroLock, { passive: true })
  window.addEventListener('keydown', onKey)
  window.addEventListener('hashchange', onScroll)
  window.addEventListener('pageshow', onScroll)

  const visibleCtas = new Set<Element>()
  ctaIo = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) visibleCtas.add(entry.target)
        else visibleCtas.delete(entry.target)
      }
      sectionCtaInView.value = visibleCtas.size > 0
    },
    { threshold: 0, rootMargin: '0px' },
  )
  document.querySelectorAll('[data-section-cta]').forEach((el) => ctaIo?.observe(el))

  footerIo = new IntersectionObserver(
    (entries) => {
      footerNear.value = entries.some((entry) => entry.isIntersecting)
    },
    { threshold: 0, rootMargin: '140px 0px 0px 0px' },
  )
  document.querySelectorAll('[data-site-footer]').forEach((el) => footerIo?.observe(el))
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('resize', updateHeroLock)
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('hashchange', onScroll)
  window.removeEventListener('pageshow', onScroll)
  ctaIo?.disconnect()
  footerIo?.disconnect()
})
</script>

<template>
  <header
    class="site-header pointer-events-none fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-4 sm:pt-4"
    :class="revealed ? 'is-revealed' : 'is-hidden'"
    :inert="!revealed || undefined"
    :aria-hidden="revealed ? undefined : 'true'"
    data-site-header
  >
    <div
      :class="[condensed ? 'glass-regular text-title' : 'glass-dark text-inverse', revealed ? 'pointer-events-auto' : 'pointer-events-none']"
      class="mx-auto flex max-w-[1120px] items-center justify-between gap-3 rounded-chrome px-3 py-2 transition-[background-color,box-shadow,border-color,color] duration-200"
    >
      <a href="#topo" class="inline-flex items-center px-1 py-0.5" :aria-label="APP_NAME">
        <BrandMark class="h-10 w-auto text-brand sm:h-11" />
      </a>

      <nav class="hidden items-center gap-1 lg:flex" aria-label="Seções">
        <a
          v-for="item in nav"
          :key="item.href"
          class="site-cta-ghost"
          :class="onHero ? 'text-inverse/85 hover:text-inverse' : ''"
          :href="item.href"
        >
          {{ item.label }}
        </a>
      </nav>

      <div class="hidden items-center gap-2 md:flex">
        <a
          class="site-cta-ghost"
          :class="onHero ? 'text-inverse/85 hover:text-inverse' : ''"
          :href="APP_LOGIN_URL"
        >
          {{ CTA_LOGIN_SHORT_LABEL }}
        </a>
        <a class="site-cta-primary" :href="APP_REGISTER_URL">{{ CTA_REGISTER_LABEL }}</a>
      </div>

      <button
        type="button"
        class="grid size-10 place-items-center rounded-[12px] md:hidden"
        :class="onHero ? 'glass-dark text-inverse' : 'glass-clear text-title'"
        :aria-expanded="open"
        aria-controls="site-menu"
        :aria-label="open ? 'Fechar menu' : 'Abrir menu'"
        @click="open = !open"
      >
        <X v-if="open" class="size-5" />
        <Menu v-else class="size-5" />
      </button>
    </div>

    <div
      v-if="open"
      id="site-menu"
      class="pointer-events-auto mx-auto mt-2 max-w-[1120px] rounded-xl p-3 md:hidden glass-regular"
    >
      <nav class="grid gap-1 text-[16px]" aria-label="Menu móvel">
        <a
          v-for="item in nav"
          :key="item.href"
          class="flex min-h-10 items-center rounded-[12px] px-3 py-2.5 text-title"
          :href="item.href"
          @click="open = false"
        >
          {{ item.label }}
        </a>
      </nav>
      <div class="mt-3 grid w-full grid-cols-1 gap-2">
        <a class="site-cta-primary w-full" :href="APP_REGISTER_URL">{{ CTA_REGISTER_LABEL }}</a>
        <a class="site-cta-secondary w-full" :href="APP_LOGIN_URL">{{ CTA_LOGIN_SHORT_LABEL }}</a>
      </div>
    </div>
  </header>

  <div
    class="pointer-events-none fixed inset-x-0 bottom-0 z-40 px-3 transition-transform duration-200 motion-reduce:transition-none md:hidden"
    :class="showSticky ? 'translate-y-0' : 'translate-y-[140%]'"
    data-sticky-cta
    :aria-hidden="!showSticky"
    style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
  >
    <div
      class="mx-auto max-w-[1120px] rounded-xl p-2 glass-regular shadow-floating"
      :class="showSticky ? 'pointer-events-auto' : 'pointer-events-none'"
    >
      <a
        class="site-cta-primary site-cta-block"
        :href="APP_REGISTER_URL"
        :tabindex="showSticky ? 0 : -1"
      >
        {{ CTA_REGISTER_LABEL }}
      </a>
    </div>
  </div>
</template>
