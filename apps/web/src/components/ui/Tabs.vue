<script setup lang="ts">
interface TabItem {
  value: string
  label: string
}

const model = defineModel<string>({ required: true })

defineProps<{
  items: TabItem[]
  disabled?: boolean
}>()
</script>

<template>
  <div class="inline-flex rounded-[10px] bg-input p-1" role="tablist">
    <button
      v-for="item in items"
      :key="item.value"
      type="button"
      role="tab"
      class="h-9 rounded-[8px] px-3.5 text-[13px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
      :class="model === item.value ? 'bg-surface text-title' : 'text-muted'"
      :aria-selected="model === item.value"
      :disabled="disabled"
      @click="model = item.value"
    >
      {{ item.label }}
    </button>
  </div>
</template>
