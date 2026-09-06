<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import SaleWizard from '@/features/sales/SaleWizard.vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  saleId?: number
}>()

const router = useRouter()
const auth = useAuthStore()

const isEdit = computed(() => Boolean(props.saleId))
const requiredPermission = computed(() => (isEdit.value ? 'sales.update' : 'sales.create'))
const allowed = computed(() => auth.can(requiredPermission.value))

function goBack() {
  if (props.saleId) {
    void router.push({ name: 'sales-show', params: { id: String(props.saleId) } })
    return
  }
  void router.push({ name: 'sales' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      :title="isEdit ? 'Continuar venda' : 'Nova venda'"
      description="Cliente, itens, valor, pagamentos e confirmação."
    >
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
      </template>
    </PageHeader>

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ isEdit ? 'editar' : 'criar' }} vendas.
    </Banner>
    <SurfaceCard v-else>
      <SaleWizard :sale-id="saleId" />
    </SurfaceCard>
  </div>
</template>
