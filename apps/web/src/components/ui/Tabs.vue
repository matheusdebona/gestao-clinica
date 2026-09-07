<script setup lang="ts">
import { cn } from '@/lib/cn'

interface TabItem {
  value: string
  label: string
}

const model = defineModel<string>({ required: true })

withDefaults(
  defineProps<{
    items: TabItem[]
    disabled?: boolean
    block?: boolean
  }>(),
  {
    disabled: false,
    block: false,
  },
)
</script>

<template>
  <div
    :class="cn('glass-field rounded-[12px] p-1', block ? 'flex w-full' : 'inline-flex')"
    role="tablist"
  >
    <button
      v-for="item in items"
      :key="item.value"
      type="button"
      role="tab"
      :class="
        cn(
          'h-9 rounded-[10px] px-3.5 text-[13px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40',
          block && 'min-w-0 flex-1 px-2',
          model === item.value ? 'glass-regular text-title' : 'border-transparent text-muted shadow-none',
        )
      "
      :aria-selected="model === item.value"
      :disabled="disabled"
      @click="model = item.value"
    >
      {{ item.label }}
    </button>
  </div>
</template>
