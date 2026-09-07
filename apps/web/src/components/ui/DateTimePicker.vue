<script setup lang="ts">
import { CalendarClock } from '@lucide/vue'
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
import {
  formatDatetimeLocal,
  minuteOptions,
  nowDatetimeLocal,
  parseDatetimeLocal,
  toDatetimeLocal,
  todayIso,
} from '@/lib/iso-date'

const HOURS = Array.from({ length: 24 }, (_, index) => index)

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
    placeholder: 'Selecionar data e hora',
    modal: true,
  },
)

const instanceId = useId()
const headingId = computed(() => `datetime-picker-heading-${props.id ?? instanceId}`)
const calendarRef = ref<{ focusSelected: () => void } | null>(null)
const hourListRef = ref<HTMLElement | null>(null)
const minuteListRef = ref<HTMLElement | null>(null)

const parsedModel = parseDatetimeLocal(model.value)
const draftDate = ref(parsedModel?.dateIso ?? '')
const draftHours = ref(parsedModel?.hours ?? 9)
const draftMinutes = ref(parsedModel?.minutes ?? 0)
const minutes = computed(() => minuteOptions(draftMinutes.value))

const display = computed(() =>
  model.value ? formatDatetimeLocal(model.value) : props.placeholder,
)

const triggerClass = computed(() =>
  cn(
    'glass-field inline-flex h-11 w-full items-center justify-between gap-2 rounded-[12px] px-3.5 text-left text-[15px] outline-none disabled:cursor-not-allowed disabled:opacity-50',
  ),
)

watch(
  () => model.value,
  (value) => {
    const next = parseDatetimeLocal(value)
    if (next) {
      draftDate.value = next.dateIso
      draftHours.value = next.hours
      draftMinutes.value = next.minutes
    }
  },
)

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }
  const next = parseDatetimeLocal(model.value)
  if (next) {
    draftDate.value = next.dateIso
    draftHours.value = next.hours
    draftMinutes.value = next.minutes
  } else {
    const now = parseDatetimeLocal(nowDatetimeLocal())
    draftDate.value = ''
    draftHours.value = now?.hours ?? 9
    draftMinutes.value = now?.minutes ?? 0
  }
  await nextTick()
  calendarRef.value?.focusSelected()
  scrollSelectedTime()
})

function scrollSelectedTime() {
  hourListRef.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'center' })
  minuteListRef.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'center' })
}

function commit(dateIso = draftDate.value, hours = draftHours.value, minutesValue = draftMinutes.value) {
  if (!dateIso) {
    return
  }
  draftDate.value = dateIso
  draftHours.value = hours
  draftMinutes.value = minutesValue
  model.value = toDatetimeLocal(dateIso, hours, minutesValue)
}

function selectDay(iso: string) {
  commit(iso, draftHours.value, draftMinutes.value)
}

function selectHour(hour: number) {
  commit(draftDate.value || todayIso(), hour, draftMinutes.value)
  void nextTick(scrollSelectedTime)
}

function selectMinute(minute: number) {
  commit(draftDate.value || todayIso(), draftHours.value, minute)
  void nextTick(scrollSelectedTime)
}

function goNow() {
  const now = nowDatetimeLocal()
  const next = parseDatetimeLocal(now)
  if (!next) {
    return
  }
  commit(next.dateIso, next.hours, next.minutes)
  void nextTick(scrollSelectedTime)
}

function clear() {
  model.value = ''
  draftDate.value = ''
  open.value = false
}

function pad(value: number) {
  return String(value).padStart(2, '0')
}

function timeItemClass(selected: boolean) {
  return cn(
    'flex h-9 w-full items-center justify-center rounded-[10px] text-[15px] tabular-nums outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
    selected ? 'bg-brand font-medium text-inverse' : 'text-title hover:bg-brand-light/70',
  )
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
      <CalendarClock class="size-4 shrink-0 text-muted" :stroke-width="1.75" aria-hidden="true" />
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
          :selected-iso="draftDate"
          :heading-id="headingId"
          @select="selectDay"
        />

        <div class="mt-3 grid grid-cols-2 gap-2 border-t border-border-divider pt-3">
          <div>
            <p class="pb-1 text-center text-[11px] font-medium text-muted">Hora</p>
            <div
              ref="hourListRef"
              class="flex max-h-40 flex-col gap-0.5 overflow-y-auto"
              role="listbox"
              aria-label="Hora"
            >
              <button
                v-for="hour in HOURS"
                :key="hour"
                type="button"
                role="option"
                :data-selected="draftHours === hour"
                :aria-selected="draftHours === hour"
                :class="timeItemClass(draftHours === hour)"
                @click="selectHour(hour)"
              >
                {{ pad(hour) }}
              </button>
            </div>
          </div>
          <div>
            <p class="pb-1 text-center text-[11px] font-medium text-muted">Minuto</p>
            <div
              ref="minuteListRef"
              class="flex max-h-40 flex-col gap-0.5 overflow-y-auto"
              role="listbox"
              aria-label="Minuto"
            >
              <button
                v-for="minute in minutes"
                :key="minute"
                type="button"
                role="option"
                :data-selected="draftMinutes === minute"
                :aria-selected="draftMinutes === minute"
                :class="timeItemClass(draftMinutes === minute)"
                @click="selectMinute(minute)"
              >
                {{ pad(minute) }}
              </button>
            </div>
          </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-2">
          <Button variant="ghost" type="button" :disabled="!model" @click="clear">
            Limpar
          </Button>
          <Button variant="secondary" type="button" @click="goNow">Agora</Button>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
