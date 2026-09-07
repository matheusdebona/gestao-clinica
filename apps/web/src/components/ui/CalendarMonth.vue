<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { computed, nextTick, ref, watch } from 'vue'
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

const props = defineProps<{
  selectedIso?: string
  headingId: string
}>()

const emit = defineEmits<{
  select: [iso: string]
}>()

const gridRef = ref<HTMLElement | null>(null)
const weekdays = weekdayHeaders('pt-BR')
const cursor = ref(parseIsoDate(props.selectedIso ?? '') ?? new Date())
const activeIso = ref(props.selectedIso || todayIso())

const heading = computed(() => monthHeading(cursor.value))
const cells = computed(() => monthGrid(cursor.value.getFullYear(), cursor.value.getMonth()))
const today = computed(() => todayIso())

watch(
  () => props.selectedIso,
  (value) => {
    const parsed = parseIsoDate(value ?? '')
    if (parsed) {
      cursor.value = parsed
      activeIso.value = value ?? todayIso()
    }
  },
)

function focusIso(iso: string | undefined) {
  if (!iso) {
    return
  }
  activeIso.value = iso
  gridRef.value?.querySelector<HTMLButtonElement>(`[data-iso="${iso}"]`)?.focus()
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

defineExpose({
  focusSelected() {
    void nextTick(() => focusIso(activeIso.value))
  },
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between gap-2">
      <IconButton label="Mês anterior" @click="shiftMonth(-1)">
        <ChevronLeft class="size-4" :stroke-width="1.75" />
      </IconButton>
      <p
        :id="headingId"
        class="min-w-0 truncate text-center text-[15px] font-medium tracking-[-0.02em] text-title"
      >
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
        class="calendar-month-day"
        role="gridcell"
        :data-iso="cell.iso"
        :tabindex="cell.iso === activeIso ? 0 : -1"
        :aria-label="formatIsoDate(cell.iso)"
        :aria-selected="selectedIso === cell.iso"
        :class="
          cn(
            'flex size-9 items-center justify-center justify-self-center rounded-full text-[13px] outline-none',
            cell.inMonth ? 'text-title' : 'text-muted/50',
            cell.iso === today && selectedIso !== cell.iso && 'ring-1 ring-brand/50',
            selectedIso === cell.iso
              ? 'bg-brand font-medium text-inverse'
              : 'hover:bg-brand-light/70',
          )
        "
        @click="emit('select', cell.iso)"
      >
        {{ cell.day }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.calendar-month-day {
  font-variant-numeric: tabular-nums;
}

.calendar-month-day:focus-visible {
  outline: 2px solid var(--sv-brand-primary);
  outline-offset: 2px;
}
</style>
