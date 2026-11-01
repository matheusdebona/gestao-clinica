<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import FormField from '@/components/ui/FormField.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { getCheckin, saveCheckin } from '@/features/daily/api'
import { formatIsoDate } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{ day: string }>()
const toast = useToastStore()
const queryClient = useQueryClient()

const { data, isPending } = useQuery({
  queryKey: computed(() => ['leads', 'checkin', props.day]),
  queryFn: () => getCheckin(props.day),
})

const form = ref({ worked: '', change: '', notes: '' })
const error = ref('')
watch(
  data,
  (value) => {
    form.value = {
      worked: value?.data?.worked ?? '',
      change: value?.data?.change ?? '',
      notes: value?.data?.notes ?? '',
    }
  },
  { immediate: true },
)

const editable = computed(() => data.value?.editable ?? false)
const saved = computed(() => data.value?.data ?? null)

const { mutate, isPending: saving } = useMutation({
  mutationFn: () =>
    saveCheckin(props.day, {
      worked: form.value.worked.trim() || null,
      change: form.value.change.trim() || null,
      notes: form.value.notes.trim() || null,
    }),
  onSuccess: async () => {
    error.value = ''
    toast.success('Check-in salvo')
    await queryClient.invalidateQueries({ queryKey: ['leads'] })
  },
  onError: (err) => {
    if (err instanceof ApiError && err.status === 422) {
      error.value = err.first('worked') || err.first('day') || err.message
      return
    }
    toast.error(err instanceof ApiError ? err.message : 'Não foi possível salvar.')
  },
})
</script>

<template>
  <SurfaceCard>
    <h2>Check-in do dia</h2>
    <p class="mt-1 text-[13px] text-muted">
      Sua análise de {{ formatIsoDate(day) }}. Opcional, um por dia, e só dá para editar no próprio dia.
    </p>
    <Skeleton v-if="isPending" class="mt-3 h-24" />
    <template v-else-if="editable">
      <form class="mt-4 flex flex-col gap-4" @submit.prevent="mutate()">
        <Banner v-if="error" variant="danger">{{ error }}</Banner>
        <FormField label="O que funcionou" html-for="checkin-worked">
          <Textarea id="checkin-worked" v-model="form.worked" placeholder="Ex.: mensagem curta no Instagram teve resposta." />
        </FormField>
        <FormField label="O que mudar" html-for="checkin-change">
          <Textarea id="checkin-change" v-model="form.change" placeholder="Ex.: ligar antes das 11h." />
        </FormField>
        <FormField label="Observações" html-for="checkin-notes">
          <Textarea id="checkin-notes" v-model="form.notes" />
        </FormField>
        <div class="flex sm:justify-end">
          <Button mobile-block type="submit" :loading="saving">
            {{ saved ? 'Atualizar check-in' : 'Salvar check-in' }}
          </Button>
        </div>
      </form>
    </template>
    <template v-else>
      <dl v-if="saved" class="mt-4 flex flex-col gap-3">
        <div v-if="saved.worked">
          <dt class="text-[13px] text-muted">O que funcionou</dt>
          <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ saved.worked }}</dd>
        </div>
        <div v-if="saved.change">
          <dt class="text-[13px] text-muted">O que mudar</dt>
          <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ saved.change }}</dd>
        </div>
        <div v-if="saved.notes">
          <dt class="text-[13px] text-muted">Observações</dt>
          <dd class="mt-0.5 whitespace-pre-wrap text-[15px] text-title">{{ saved.notes }}</dd>
        </div>
      </dl>
      <p v-else class="mt-3 text-[15px] text-body">Nenhum check-in neste dia.</p>
    </template>
  </SurfaceCard>
</template>
