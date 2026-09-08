<script setup lang="ts">
import { Menu, X } from '@lucide/vue'
import { onMounted, onUnmounted, ref } from 'vue'
import { APP_LOGIN_URL, APP_NAME, APP_REGISTER_URL } from '../lib/urls'

const open = ref(false)
const condensed = ref(false)

function onScroll() {
  condensed.value = window.scrollY > 24
}

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <header class="pointer-events-none fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-4 sm:pt-4">
    <div
      class="pointer-events-auto mx-auto flex max-w-[1120px] items-center justify-between gap-3 rounded-chrome px-3 py-2 transition-[background-color,box-shadow,border-color] duration-200"
      :class="condensed ? 'glass-regular' : 'glass-clear'"
    >
      <a href="#topo" class="flex items-center gap-2.5 rounded-[10px] px-1 py-1 text-title">
        <span class="grid size-8 place-items-center rounded-[9px] bg-brand text-[15px] font-semibold text-inverse">
          H
        </span>
        <span class="text-[15px] font-semibold tracking-[-0.03em]">{{ APP_NAME }}</span>
      </a>

      <nav class="hidden items-center gap-1 md:flex" aria-label="Seções">
        <a class="site-cta-ghost" href="#produto">Produto</a>
        <a class="site-cta-ghost" href="#clinicas">Clínicas</a>
        <a class="site-cta-ghost" href="#acesso">Acesso</a>
        <a class="site-cta-ghost" href="#faq">FAQ</a>
      </nav>

      <div class="hidden items-center gap-2 md:flex">
        <a class="site-cta-ghost" :href="APP_LOGIN_URL">Entrar</a>
        <a class="site-cta-primary" :href="APP_REGISTER_URL">Criar clínica</a>
      </div>

      <button
        type="button"
        class="grid size-10 place-items-center rounded-[12px] text-title md:hidden glass-clear"
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
      <nav class="grid gap-1 text-[15px]" aria-label="Menu móvel">
        <a class="rounded-[12px] px-3 py-2.5 text-title" href="#produto" @click="open = false">Produto</a>
        <a class="rounded-[12px] px-3 py-2.5 text-title" href="#clinicas" @click="open = false">Clínicas</a>
        <a class="rounded-[12px] px-3 py-2.5 text-title" href="#acesso" @click="open = false">Acesso</a>
        <a class="rounded-[12px] px-3 py-2.5 text-title" href="#faq" @click="open = false">FAQ</a>
      </nav>
      <div class="mt-3 grid gap-2">
        <a class="site-cta-secondary glass-clear" :href="APP_LOGIN_URL">Entrar</a>
        <a class="site-cta-primary" :href="APP_REGISTER_URL">Criar clínica</a>
      </div>
    </div>
  </header>
</template>
