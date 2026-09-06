<script setup lang="ts">
import { cn } from '@/lib/cn'
import { Eye, EyeOff } from '@lucide/vue'
import { computed, ref, useAttrs } from 'vue'
import IconButton from '@/components/ui/IconButton.vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string
    invalid?: boolean
    disabled?: boolean
    placeholder?: string
    id?: string
    autocomplete?: string
  }>(),
  {
    modelValue: '',
    invalid: false,
    disabled: false,
    placeholder: '',
    autocomplete: 'current-password',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()
const visible = ref(false)

const inputType = computed(() => (visible.value ? 'text' : 'password'))

const classes = computed(() =>
  cn(
    'h-11 w-full rounded-[10px] border bg-input py-0 pr-11 pl-3.5 text-[15px] text-title transition-colors placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50',
    props.invalid
      ? 'border-danger/50 bg-danger-light'
      : 'border-transparent focus-visible:border-brand/40 focus-visible:bg-surface',
  ),
)

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function toggleVisible() {
  if (props.disabled) {
    return
  }
  visible.value = !visible.value
}
</script>

<template>
  <div class="relative w-full">
    <input
      :id="id"
      :type="inputType"
      :class="classes"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :autocomplete="autocomplete"
      :aria-invalid="invalid || undefined"
      v-bind="attrs"
      @input="onInput"
    >
    <div class="absolute top-1/2 right-1.5 -translate-y-1/2">
      <IconButton
        type="button"
        :label="visible ? 'Ocultar senha' : 'Mostrar senha'"
        :disabled="disabled"
        @click="toggleVisible"
      >
        <EyeOff v-if="visible" class="size-4 text-muted" :stroke-width="1.75" />
        <Eye v-else class="size-4 text-muted" :stroke-width="1.75" />
      </IconButton>
    </div>
  </div>
</template>
