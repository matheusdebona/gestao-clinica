<script setup lang="ts">
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { Check, ChevronDown, Copy, ShieldCheck } from '@lucide/vue'
import { computed, ref } from 'vue'
import ActionBar from '@/components/ui/ActionBar.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import InfoTip from '@/components/ui/InfoTip.vue'
import Input from '@/components/ui/Input.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { formatClinicDateTime, formatRelativeTime } from '@/lib/formatters'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'
import {
  INTEGRATIONS_KEY,
  revokeClaudeConnection,
  toolLabel,
  type Integration,
  type IntegrationConnection,
} from './api'

const props = defineProps<{ integration: Integration }>()

const toast = useToastStore()
const queryClient = useQueryClient()

const connected = computed(() => props.integration.status === 'connected')
const copied = ref(false)
const guideOpen = ref(false)
const confirmOpen = ref(false)
const target = ref<IntegrationConnection | null>(null)

const READS = [
  'Faturamento, vendas por produto e DRE',
  'Agenda, leads, pacientes (nome, faixa e histórico)',
  'Metas, remuneração variável, estoque baixo, retornos e orçamentos',
]

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(props.integration.server_url)
    copied.value = true
    toast.success('Endereço copiado')
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    toast.error('Não foi possível copiar. Selecione o endereço e copie.')
  }
}

function askRevoke(connection: IntegrationConnection) {
  target.value = connection
  confirmOpen.value = true
}

const { mutate: revoke, isPending: revoking } = useMutation({
  mutationFn: (c: IntegrationConnection) => revokeClaudeConnection(c.user_id, c.client_id),
  onSuccess: async () => {
    toast.success('Acesso do Claude revogado')
    await queryClient.invalidateQueries({ queryKey: INTEGRATIONS_KEY })
  },
  onError: (error) => {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível revogar.')
  },
})
</script>

<template>
  <SurfaceCard :padding="false" data-testid="integration-claude">
    <div class="flex flex-col gap-5 p-5">
      <div class="flex items-start gap-3">
        <div
          class="flex size-11 shrink-0 items-center justify-center rounded-[12px] bg-[#d97757] text-[20px] font-semibold text-white"
          aria-hidden="true"
        >
          ✳
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-[17px] font-semibold tracking-[-0.02em] text-title">Claude</h2>
            <Badge :variant="connected ? 'success' : 'muted'" data-testid="claude-status">
              {{ connected ? 'Conectado' : 'Não conectado' }}
            </Badge>
            <Badge variant="purple">Só leitura</Badge>
          </div>
          <p class="mt-1 text-[14px] text-muted">
            Pergunte ao Claude sobre faturamento, agenda, leads e pacientes da clínica.
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <div class="flex items-center gap-1.5">
          <span class="text-[13px] font-medium text-title">Endereço do servidor (URL)</span>
          <InfoTip
            text="Cole este endereço no Claude em Personalizar › Conectores › Adicionar conector personalizado. O Claude só consegue ler; nada é cadastrado ou alterado no HOF Pay."
          />
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            :model-value="integration.server_url"
            readonly
            aria-label="Endereço do servidor MCP"
            class="min-w-0 flex-1 font-mono"
            data-testid="claude-server-url"
            @focus="($event.target as HTMLInputElement).select()"
          />
          <Button variant="secondary" mobile-block @click="copyUrl">
            <Check v-if="copied" class="size-4" aria-hidden="true" />
            <Copy v-else class="size-4" aria-hidden="true" />
            {{ copied ? 'Copiado' : 'Copiar' }}
          </Button>
        </div>
      </div>

      <div class="rounded-[12px] border border-border-divider">
        <button
          type="button"
          class="flex min-h-10 w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-[14px] font-medium text-title"
          :aria-expanded="guideOpen"
          aria-controls="claude-guide"
          @click="guideOpen = !guideOpen"
        >
          Como conectar no Claude
          <ChevronDown
            :class="['size-4 text-muted transition-transform', guideOpen && 'rotate-180']"
            aria-hidden="true"
          />
        </button>
        <ol
          v-show="guideOpen"
          id="claude-guide"
          class="list-decimal space-y-2 border-t border-border-divider px-4 pt-3 pb-4 pl-9 text-[14px] text-body"
          data-testid="claude-guide"
        >
          <li>No Claude (site, app do computador ou celular), abra <b>Personalizar › Conectores</b>.</li>
          <li>Clique em <b>+ Adicionar</b> e depois em <b>Adicionar conector personalizado</b>.</li>
          <li>Em Nome, escreva <b>HOF Pay</b>; em URL, cole o endereço acima. Clique em <b>Continuar</b>.</li>
          <li>
            Em Autenticação, deixe o login ligado. Na identificação OAuth, escolha
            <b>Registrar automaticamente</b> (<i>Register automatically</i>).
            Clique em <b>Adicionar</b>.
          </li>
          <li>
            Clique em <b>Conectar</b>. Entre com o seu login do HOF Pay, confira a clínica e clique em
            <b>Permitir acesso</b>.
          </li>
          <li>
            Numa conversa, toque em <b>+ › Conectores</b>, ligue o HOF Pay e pergunte, por exemplo:
            <i>“Quanto faturamos este mês?”</i> ou <i>“Quais orçamentos estão sem resposta?”</i>
          </li>
          <li class="text-muted">
            Em plano Team ou Enterprise do Claude, quem é Owner adiciona o conector primeiro em
            Configurações da organização › Conectores; depois cada pessoa clica em Conectar.
          </li>
        </ol>
      </div>

      <div class="flex items-start gap-2 rounded-[12px] bg-surface-muted px-4 py-3 text-[13px] text-muted">
        <ShieldCheck class="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
        <div>
          <p class="font-medium text-title">O que o Claude consegue ler</p>
          <ul class="mt-1 list-disc space-y-0.5 pl-4">
            <li v-for="item in READS" :key="item">{{ item }}</li>
          </ul>
          <p class="mt-1.5">
            Telefones, e-mails, CPF e anotações clínicas não são enviados. Só administradores conectam,
            e o acesso pode ser revogado aqui a qualquer momento.
          </p>
        </div>
      </div>
    </div>

    <div class="border-t border-border-divider px-5 py-4">
      <h3 class="text-[14px] font-semibold text-title">Conexões ativas</h3>
      <p v-if="integration.connections.length === 0" class="mt-1 text-[14px] text-muted" data-testid="claude-no-connections">
        Ninguém conectou o Claude ainda.
      </p>
      <ul v-else class="mt-2 divide-y divide-border-divider" data-testid="claude-connections">
        <li
          v-for="c in integration.connections"
          :key="c.id"
          class="flex flex-col gap-3 py-3 sm:flex-row-reverse sm:items-center sm:justify-between"
        >
          <div class="min-w-0 sm:flex-1 sm:pl-4">
            <p class="text-[14px] font-medium text-title">{{ c.user_name }} · {{ c.client_name }}</p>
            <p class="text-[13px] text-muted">
              Conectado em {{ formatClinicDateTime(c.connected_at) }} ·
              {{ c.last_used_at ? `último uso ${formatRelativeTime(c.last_used_at)}` : 'ainda não usado' }} ·
              {{ c.calls }} {{ c.calls === 1 ? 'consulta' : 'consultas' }}
            </p>
          </div>
          <ActionBar>
            <template #negative>
              <Button
                variant="destructive"
                :loading="revoking && target?.id === c.id"
                data-testid="claude-revoke"
                @click="askRevoke(c)"
              >
                Revogar
              </Button>
            </template>
          </ActionBar>
        </li>
      </ul>
    </div>

    <div v-if="integration.recent_calls.length" class="border-t border-border-divider px-5 py-4">
      <h3 class="text-[14px] font-semibold text-title">Últimas consultas do Claude</h3>
      <ul class="mt-2 space-y-1.5 text-[13px]" data-testid="claude-recent-calls">
        <li v-for="call in integration.recent_calls" :key="call.id" class="flex items-center justify-between gap-3">
          <span class="min-w-0 truncate text-body">
            {{ toolLabel(call.tool) }}
            <span class="text-muted">· {{ call.user_name ?? '—' }}</span>
          </span>
          <span class="flex shrink-0 items-center gap-2 text-muted">
            <Badge v-if="call.status !== 'ok'" variant="warning">erro</Badge>
            {{ formatClinicDateTime(call.created_at) }}
          </span>
        </li>
      </ul>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      stack-footer
      title="Revogar acesso do Claude?"
      :description="`${target?.user_name ?? ''} vai precisar conectar de novo para o Claude voltar a ler os dados da clínica.`"
      confirm-label="Revogar"
      @confirm="target && revoke(target)"
    />
  </SurfaceCard>
</template>
