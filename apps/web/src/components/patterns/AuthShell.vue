<script setup lang="ts">
import authClinic from '@/assets/auth-clinic.jpg'
import BrandMark from '@/components/ui/BrandMark.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import { APP_NAME } from '@/lib/brand'

/**
 * Same card as the login screen (image on the left on desktop, form on the
 * right; form only on mobile). Used by "Esqueceu a senha?" and "Redefinir senha".
 */
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="relative flex min-h-dvh items-center justify-center px-4 py-[5dvh]">
    <div class="absolute top-4 right-4 z-10">
      <ThemeToggle />
    </div>
    <div class="glass-regular auth-shell h-[90dvh] w-full max-w-[920px] overflow-hidden rounded-[22px]">
      <div class="auth-shell-image">
        <img :src="authClinic" alt="" class="size-full object-cover">
        <div class="absolute inset-0 bg-sidebar/40" />
        <p class="absolute bottom-5 left-5 right-5 text-[15px] font-medium tracking-[-0.02em] text-inverse">
          {{ APP_NAME }}. Gestão da clínica, com calma.
        </p>
      </div>

      <section class="auth-shell-content flex min-h-0 flex-col overflow-y-auto px-6 py-8 md:px-10 md:py-10">
        <div class="my-auto flex w-full flex-col">
          <div class="mb-8 flex justify-center">
            <BrandMark size="3xl" :decorative="false" />
          </div>
          <div class="mx-auto w-full max-w-[360px]">
            <p class="text-center text-[12px] font-semibold tracking-[0.16em] uppercase text-brand">
              {{ APP_NAME }}
            </p>
            <h1 class="mt-2 text-[28px] font-semibold tracking-[-0.03em] text-title">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-[15px] text-muted">{{ subtitle }}</p>
            <slot />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  display: grid;
  grid-template-rows: minmax(0, 1fr);
  grid-template-columns: 1fr;
}

.auth-shell-image {
  display: none;
}

.auth-shell-content {
  grid-row: 1;
  grid-column: 1;
  min-height: 0;
  align-self: stretch;
}

@media (min-width: 768px) {
  .auth-shell {
    position: relative;
    grid-template-columns: 1fr 1fr;
  }

  .auth-shell-image {
    display: block;
    position: relative;
    grid-row: 1;
    grid-column: 1;
  }

  .auth-shell-content {
    grid-column: 2;
    height: 100%;
    display: flex;
  }
}
</style>
