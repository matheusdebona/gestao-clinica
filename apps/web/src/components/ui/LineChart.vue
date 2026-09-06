<script setup lang="ts">
import {
  CategoryScale,
  Chart,
  Filler,
  LinearScale,
  LineController,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

Chart.register(CategoryScale, LinearScale, LineController, LineElement, PointElement, Filler, Tooltip)

const props = defineProps<{
  labels: string[]
  values: number[]
  label: string
  formatValue?: (value: number) => string
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart<'line'> | null = null

function formatTick(value: number): string {
  return props.formatValue ? props.formatValue(value) : String(value)
}

function readToken(name: string, fallback: string): string {
  if (typeof window === 'undefined') {
    return fallback
  }
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback
}

function withAlpha(color: string, alpha: number): string {
  const hex = color.replace('#', '').trim()
  if (hex.length === 6 && /^[0-9a-fA-F]+$/.test(hex)) {
    const r = Number.parseInt(hex.slice(0, 2), 16)
    const g = Number.parseInt(hex.slice(2, 4), 16)
    const b = Number.parseInt(hex.slice(4, 6), 16)
    return `rgba(${r}, ${g}, ${b}, ${alpha})`
  }
  return color
}

function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function render() {
  if (!canvas.value) {
    return
  }

  const brand = readToken('--sv-brand-primary', 'currentColor')
  const muted = readToken('--sv-text-muted', 'currentColor')
  const grid = readToken('--sv-border-subtle', 'transparent')
  const title = readToken('--sv-text-title', 'currentColor')

  chart?.destroy()
  chart = new Chart(canvas.value, {
    type: 'line',
    data: {
      labels: props.labels,
      datasets: [
        {
          data: props.values,
          borderColor: brand,
          backgroundColor: withAlpha(brand, 0.12),
          borderWidth: 2,
          pointRadius: props.values.length > 40 ? 0 : 2,
          pointHoverRadius: 4,
          pointBackgroundColor: brand,
          tension: 0.3,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: prefersReducedMotion() ? false : { duration: 280 },
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: title,
          titleColor: readToken('--sv-text-inverse', 'white'),
          bodyColor: readToken('--sv-text-inverse', 'white'),
          displayColors: false,
          callbacks: {
            label: (item) => formatTick(Number(item.parsed.y ?? 0)),
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: muted,
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 6,
            font: { size: 11 },
          },
          border: { display: false },
        },
        y: {
          beginAtZero: true,
          grid: { color: grid },
          ticks: {
            color: muted,
            font: { size: 11 },
            callback: (value) => formatTick(Number(value)),
          },
          border: { display: false },
        },
      },
    },
  })
}

onMounted(() => {
  void nextTick(render)
})

watch(
  () => [props.labels, props.values] as const,
  () => {
    render()
  },
  { deep: true },
)

onBeforeUnmount(() => {
  chart?.destroy()
  chart = null
})
</script>

<template>
  <div class="relative h-44 w-full">
    <canvas ref="canvas" :aria-label="label" role="img" />
  </div>
</template>
