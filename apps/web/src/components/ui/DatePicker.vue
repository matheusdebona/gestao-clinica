<script setup lang="ts">
import { Calendar as CalendarIcon } from '@lucide/vue'
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
import { computed, nextTick, ref, useId, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import CalendarMonth from '@/components/ui/CalendarMonth.vue'
import { cn } from '@/lib/cn'
import { formatIsoDate } from '@/lib/formatters'
import { todayIso } from '@/lib/iso-date'

const model = defineModel<string>({ default: '' })
const open = defineModel<boolean>('open', { default: false })

const props = withDefaults(
  defineProps<{
    id?: string
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
    modal?: boolean
  }>(),
  {
    invalid: false,
    disabled: false,
    placeholder: 'Selecionar data',
    modal: true,
  },
)

const instanceId = useId()
const headingId = computed(() => `date-picker-heading-${props.id ?? instanceId}`)
const calendarRef = ref<{ focusSelected: () => void } | null>(null)
const display = computed(() => (model.value ? formatIsoDate(model.value) : props.placeholder))

const triggerClass = computed(() =>
  cn(
    'glass-field inline-flex h-11 w-full items-center justify-between gap-2 rounded-[12px] px-3.5 text-left text-[15px] outline-none disabled:cursor-not-allowed disabled:opacity-50',
  ),
)

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }
  await nextTick()
  calendarRef.value?.focusSelected()
})

function selectDay(iso: string) {
  model.value = iso
  open.value = false
}

function goToday() {
  selectDay(todayIso())
}

function clear() {
  model.value = ''
  open.value = false
}
</script>

<template>
  <PopoverRoot v-model:open="open" :modal="modal">
    <PopoverTrigger
      :id="id"
      :class="triggerClass"
      :disabled="disabled"
      :aria-invalid="invalid || undefined"
      aria-haspopup="dialog"
    >
      <span :class="model ? 'truncate text-title' : 'truncate text-muted'">{{ display }}</span>
      <CalendarIcon class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        class="glass-menu z-50 w-[min(calc(100vw-2rem),20.5rem)] rounded-[14px] p-3 outline-none"
        :side-offset="8"
        align="start"
        :collision-padding="12"
        :aria-labelledby="headingId"
      >
        <CalendarMonth
          ref="calendarRef"
          :selected-iso="model"
          :heading-id="headingId"
          @select="selectDay"
        />
        <div class="mt-3 flex items-center justify-between gap-2">
          <Button variant="ghost" type="button" :disabled="!model" @click="clear">
            Limpar
          </Button>
          <Button variant="secondary" type="button" @click="goToday">Hoje</Button>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
