<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import RankBar from '@/components/patterns/RankBar.vue'
import StockStatusBadge from '@/components/patterns/StockStatusBadge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import MetricCard from '@/components/patterns/MetricCard.vue'
import { maxNumeric, rankRatio } from '@/features/metrics/chart'
import { SESSION_STATUS_ORDER, sessionStatusLabel } from '@/features/metrics/labels'
import { formatBRL, formatPercent, formatQty } from '@/lib/formatters'
import type { InventoryMetrics, OperationsMetrics } from '@/types/metrics'

const SHORT_LIST = 5

const props = defineProps<{
  inventory?: InventoryMetrics
  operations?: OperationsMetrics
  inventoryPending: boolean
  operationsPending: boolean
  inventoryError: boolean
  operationsError: boolean
}>()

const router = useRouter()

const lowStock = computed(() => props.inventory?.low_stock_products.slice(0, SHORT_LIST) ?? [])
const pending = computed(() => props.operations?.pending_fulfillments.slice(0, SHORT_LIST) ?? [])
const professionals = computed(() => props.operations?.by_professional.slice(0, SHORT_LIST) ?? [])
const professionalMax = computed(() => maxNumeric(professionals.value.map((row) => row.sessions_count)))

const sessionRows = computed(() =>
  SESSION_STATUS_ORDER.map((status) => ({
    status,
    label: sessionStatusLabel(status),
    count: props.operations?.sessions_by_status[status] ?? 0,
  })),
)

function openProduct(id: number) {
  void router.push({ name: 'products-show', params: { id: String(id) } })
}

function openTreatment(id: number) {
  void router.push({ name: 'treatments-show', params: { id: String(id) } })
}

function openProducts() {
  void router.push({ name: 'products' })
}

function openTreatments() {
  void router.push({ name: 'treatments' })
}

function openAgenda() {
  void router.push({ name: 'appointments' })
}

function lowStockMeta(product: (typeof lowStock.value)[number]) {
  const coverage = product.coverage_days
    ? `cobertura ${formatQty(product.coverage_days)} d`
    : 'sem consumo no período'
  return `${formatQty(product.stock_quantity)} · mín. ${formatQty(product.min_stock)} · ${coverage}`
}

function pendingMeta(row: (typeof pending.value)[number]) {
  return `Tratamento #${row.treatment_id} · ${formatQty(row.remaining_units)} a aplicar`
}
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <h2>Estoque e operações</h2>
      <div class="flex flex-wrap gap-1">
        <Button variant="ghost" @click="openProducts">Produtos</Button>
        <Button variant="ghost" @click="openTreatments">Tratamentos</Button>
        <Button variant="ghost" @click="openAgenda">Agenda</Button>
      </div>
    </div>

    <Banner v-if="inventoryError" variant="danger" title="Estoque">
      Não foi possível carregar o estoque.
    </Banner>
    <Banner v-if="operationsError" variant="danger" title="Operações">
      Não foi possível carregar as sessões.
    </Banner>

    <div v-if="inventoryPending || operationsPending" class="grid grid-cols-2 gap-3">
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
      <Skeleton class="h-24" />
    </div>

    <template v-else>
      <div v-if="inventory" class="grid grid-cols-2 gap-3">
        <MetricCard label="Estoque baixo">
          {{ inventory.kpis.low_stock_count }}
        </MetricCard>
        <MetricCard label="Valor em estoque">
          <MoneyDisplay size="lg" :value="inventory.kpis.inventory_value" />
        </MetricCard>
        <MetricCard label="Estoque negativo">
          {{ inventory.kpis.negative_stock_count }}
        </MetricCard>
        <MetricCard label="Consumo no período" hint="unidades baixadas em sessão">
          {{ formatQty(inventory.kpis.consumption_quantity) }}
        </MetricCard>
      </div>

      <div v-if="operations" class="grid grid-cols-2 gap-3">
        <MetricCard label="Sessões" hint="agendadas no período">
          {{ operations.kpis.sessions_total }}
        </MetricCard>
        <MetricCard label="Cancelamentos">
          {{ formatPercent(operations.kpis.cancellation_rate) }}
        </MetricCard>
        <MetricCard
          label="Saldo a aplicar"
          :hint="`${operations.kpis.pending_fulfillment_treatments_count} tratamentos`"
        >
          {{ formatQty(operations.kpis.pending_fulfillment_units) }}
        </MetricCard>
        <MetricCard label="Concluídas">
          {{ operations.sessions_by_status.completed ?? 0 }}
        </MetricCard>
      </div>

      <SurfaceCard v-if="operations">
        <p class="text-[13px] font-medium text-muted">Sessões por situação</p>
        <div class="mt-2 divide-y divide-border-divider">
          <RankBar
            v-for="row in sessionRows"
            :key="row.status"
            :label="row.label"
            :value="String(row.count)"
            :ratio="rankRatio(row.count, operations.kpis.sessions_total)"
          />
        </div>
      </SurfaceCard>

      <div>
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3>Estoque baixo</h3>
          <Button variant="ghost" @click="openProducts">Ver produtos</Button>
        </div>
        <SurfaceCard :padding="false">
          <EmptyState
            v-if="!inventory || lowStock.length === 0"
            title="Nenhum produto abaixo do mínimo"
            description="O estoque atual está acima do ponto de reposição."
          />
          <div v-else class="divide-y divide-border-divider px-5 py-2">
            <ListCard
              v-for="product in lowStock"
              :key="product.id"
              :title="product.name"
              :meta="lowStockMeta(product)"
              @action="openProduct(product.id)"
            >
              <template #status>
                <StockStatusBadge is-low-stock :stock-quantity="product.stock_quantity" />
              </template>
            </ListCard>
          </div>
        </SurfaceCard>
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3>Saldo a aplicar</h3>
          <Button variant="ghost" @click="openTreatments">Ver tratamentos</Button>
        </div>
        <SurfaceCard :padding="false">
          <EmptyState
            v-if="!operations || pending.length === 0"
            title="Nenhum pacote pendente"
            description="Não há tratamentos abertos com unidades a aplicar."
          />
          <div v-else class="divide-y divide-border-divider px-5 py-2">
            <ListCard
              v-for="row in pending"
              :key="row.treatment_id"
              :title="row.client_name || `Cliente #${row.client_id}`"
              :meta="pendingMeta(row)"
              @action="openTreatment(row.treatment_id)"
            />
          </div>
        </SurfaceCard>
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between gap-2">
          <h3>Por profissional</h3>
          <Button variant="ghost" @click="openAgenda">Ver agenda</Button>
        </div>
        <SurfaceCard>
          <EmptyState
            v-if="professionals.length === 0"
            title="Nenhuma sessão concluída"
            description="Não há produção por profissional neste período."
          />
          <div v-else class="divide-y divide-border-divider">
            <RankBar
              v-for="row in professionals"
              :key="row.professional_user_id"
              :label="row.name || `Profissional #${row.professional_user_id}`"
              :meta="formatBRL(row.total_cost)"
              :value="`${row.sessions_count} sessões`"
              :ratio="rankRatio(row.sessions_count, professionalMax)"
            />
          </div>
        </SurfaceCard>
      </div>
    </template>
  </section>
</template>
