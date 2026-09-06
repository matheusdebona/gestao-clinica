<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ListCard from '@/components/ui/ListCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { listUsers } from '@/features/users/api'
import { roleLabel } from '@/features/users/schema'
import { useAuthStore } from '@/stores/auth'
import type { ClinicUser } from '@/types/team-user'

const router = useRouter()
const auth = useAuthStore()

const page = ref(1)

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['users', page],
  queryFn: () => listUsers({ page: page.value }),
  enabled: computed(() => auth.can('users.view')),
})

const users = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)

watch(page, () => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
})

function openUser(id: number) {
  void router.push({ name: 'users-show', params: { id: String(id) } })
}

function goNew() {
  void router.push({ name: 'users-new' })
}

function userMeta(user: ClinicUser) {
  const roles = (user.roles ?? [])
    .filter((role) => role !== 'super-admin')
    .map((role) => (role === 'admin' ? 'Admin' : roleLabel(role)))
    .join(', ')
  const status = user.is_active ? 'Ativo' : 'Inativo'
  return [user.email, roles || 'Sem papel', status].join(' · ')
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader title="Equipe" :description="total ? `${total} na clínica` : undefined">
      <template #actions>
        <PermissionGate permission="users.create">
          <Button @click="goNew">Novo</Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('users.view')" variant="danger" title="Sem permissão">
      Você não pode ver a equipe.
    </Banner>

    <template v-else>
      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-4">
          <Skeleton class="h-14 w-full" />
          <Skeleton class="h-14 w-full" />
          <Skeleton class="h-14 w-full" />
        </div>
      </SurfaceCard>

      <EmptyState
        v-else-if="!users.length"
        title="Nenhum usuário"
        description="Cadastre a equipe da clínica."
      >
        <template #action>
          <PermissionGate permission="users.create">
            <Button @click="goNew">Novo usuário</Button>
          </PermissionGate>
        </template>
      </EmptyState>

      <SurfaceCard v-else :padding="false">
        <ListCard
          v-for="user in users"
          :key="user.id"
          :title="user.name"
          :meta="userMeta(user)"
          @click="openUser(user.id)"
        />
      </SurfaceCard>

      <Pagination
        v-if="lastPage > 1"
        :page="page"
        :last-page="lastPage"
        :disabled="isFetching"
        @update:page="(value) => (page = value)"
      />
    </template>
  </div>
</template>
