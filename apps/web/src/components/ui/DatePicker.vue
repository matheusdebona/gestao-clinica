<script setup lang="ts">
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from '@lucide/vue'
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
import { computed, nextTick, ref, useId, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import IconButton from '@/components/ui/IconButton.vue'
import { cn } from '@/lib/cn'
import { formatIsoDate } from '@/lib/formatters'
import {
  addMonths,
  monthGrid,
  monthHeading,
  parseIsoDate,
  todayIso,
  weekdayHeaders,
} from '@/lib/iso-date'

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
const gridRef = ref<HTMLElement | null>(null)
const weekdays = weekdayHeaders('pt-BR')
const cursor = ref(parseIsoDate(model.value) ?? new Date())
const activeIso = ref(model.value || todayIso())

const heading = computed(() => monthHeading(cursor.value))
const cells = computed(() => monthGrid(cursor.value.getFullYear(), cursor.value.getMonth()))
const today = computed(() => todayIso())
const display = computed(() => (model.value ? formatIsoDate(model.value) : props.placeholder))

const triggerClass = computed(() =>
  cn(
    'glass-field inline-flex h-11 w-full items-center justify-between gap-2 rounded-[12px] px-3.5 text-left text-[15px] outline-none disabled:cursor-not-allowed disabled:opacity-50',
  ),
)

watch(
  () => model.value,
  (value) => {
    const parsed = parseIsoDate(value)
    if (parsed) {
      cursor.value = parsed
      activeIso.value = value
    }
  },
)

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }
  cursor.value = parseIsoDate(model.value) ?? new Date()
  activeIso.value = model.value || todayIso()
  await nextTick()
  focusIso(activeIso.value)
})

function focusIso(iso: string | undefined) {
  if (!iso) {
    return
  }
  activeIso.value = iso
  gridRef.value?.querySelector<HTMLButtonElement>(`[data-iso="${iso}"]`)?.focus()
}

function selectDay(iso: string) {
  model.value = iso
  open.value = false
}

function goToday() {
  selectDay(today.value)
}

function clear() {
  model.value = ''
  open.value = false
}

function shiftMonth(amount: number) {
  cursor.value = addMonths(cursor.value, amount)
}

function onGridKeydown(event: KeyboardEvent) {
  const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End']
  if (!keys.includes(event.key)) {
    return
  }
  const current = (event.target as HTMLElement | null)?.dataset.iso
  if (!current) {
    return
  }
  const list = cells.value
  const index = list.findIndex((cell) => cell.iso === current)
  if (index < 0) {
    return
  }
  event.preventDefault()
  if (event.key === 'Home') {
    const first = list.find((cell) => cell.inMonth)
    void nextTick(() => focusIso(first?.iso))
    return
  }
  if (event.key === 'End') {
    const last = [...list].reverse().find((cell) => cell.inMonth)
    void nextTick(() => focusIso(last?.iso))
    return
  }
  let next = index
  if (event.key === 'ArrowLeft') {
    next -= 1
  }
  if (event.key === 'ArrowRight') {
    next += 1
  }
  if (event.key === 'ArrowUp') {
    next -= 7
  }
  if (event.key === 'ArrowDown') {
    next += 7
  }
  if (next < 0) {
    shiftMonth(-1)
    void nextTick(() => {
      const nextCells = cells.value
      focusIso(nextCells[nextCells.length + next]?.iso ?? nextCells.at(-1)?.iso)
    })
    return
  }
  if (next >= list.length) {
    const overflow = next - list.length
    shiftMonth(1)
    void nextTick(() => {
      focusIso(cells.value[overflow]?.iso ?? cells.value[0]?.iso)
    })
    return
  }
  focusIso(list[next]?.iso)
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
        <div class="flex items-center justify-between gap-2">
          <IconButton label="Mês anterior" @click="shiftMonth(-1)">
            <ChevronLeft class="size-4" :stroke-width="1.75" />
          </IconButton>
          <p :id="headingId" class="min-w-0 truncate text-center text-[15px] font-medium tracking-[-0.02em] text-title">
            {{ heading }}
          </p>
          <IconButton label="Próximo mês" @click="shiftMonth(1)">
            <ChevronRight class="size-4" :stroke-width="1.75" />
          </IconButton>
        </div>

        <div
          ref="gridRef"
          class="mt-3 grid grid-cols-7 gap-0.5"
          role="grid"
          :aria-labelledby="headingId"
          @keydown="onGridKeydown"
        >
          <div
            v-for="weekday in weekdays"
            :key="weekday.long"
            class="pb-1 text-center text-[11px] font-medium text-muted"
            role="columnheader"
            :aria-label="weekday.long"
          >
            {{ weekday.short }}
          </div>
          <button
            v-for="cell in cells"
            :key="cell.iso"
            type="button"
            class="date-picker-day"
            role="gridcell"
            :data-iso="cell.iso"
            :tabindex="cell.iso === activeIso ? 0 : -1"
            :aria-label="formatIsoDate(cell.iso)"
            :aria-selected="model === cell.iso"
            :class="
              cn(
                'flex size-9 items-center justify-center justify-self-center rounded-full text-[13px] outline-none',
                cell.inMonth ? 'text-title' : 'text-muted/50',
                cell.iso === today && model !== cell.iso && 'ring-1 ring-brand/50',
                model === cell.iso
                  ? 'bg-brand font-medium text-inverse'
                  : 'hover:bg-brand-light/70',
              )
            "
            @click="selectDay(cell.iso)"
          >
            {{ cell.day }}
          </button>
        </div>

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

<style scoped>
.date-picker-day {
  font-variant-numeric: tabular-nums;
}

.date-picker-day:focus-visible {
  outline: 2px solid var(--sv-brand-primary);
  outline-offset: 2px;
}
</style>
