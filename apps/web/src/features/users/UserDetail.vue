<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import PermissionGate from '@/components/patterns/PermissionGate.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import { deactivateUser, getUser, updateUser } from '@/features/users/api'
import { permissionLabel, roleLabel } from '@/features/users/schema'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'

const props = defineProps<{
  userId: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const confirmOpen = ref(false)

const idRef = computed(() => props.userId)

const { data: user, isPending, isError } = useQuery({
  queryKey: ['users', idRef],
  queryFn: () => getUser(idRef.value),
  enabled: computed(() => auth.can('users.view')),
})

const isClinicAdmin = computed(() => Boolean(user.value?.roles?.includes('admin')))
const isSelf = computed(() => user.value?.id === auth.user?.id)
const canDeactivate = computed(
  () => Boolean(user.value?.is_active) && !isClinicAdmin.value && !isSelf.value,
)

const deactivateMutation = useMutation({
  mutationFn: () => deactivateUser(props.userId),
  onSuccess: async () => {
    toast.success('Usuário desativado')
    await queryClient.invalidateQueries({ queryKey: ['users'] })
    await router.push({ name: 'users' })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para desativar.')
      return
    }
    if (error instanceof ApiError && error.status === 422) {
      toast.error(error.first('user') || error.message)
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível desativar.')
  },
})

const { mutate: reactivate, isPending: reactivating } = useMutation({
  mutationFn: () => updateUser(props.userId, { is_active: true }),
  onSuccess: async () => {
    toast.success('Usuário reativado')
    await queryClient.invalidateQueries({ queryKey: ['users'] })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão para atualizar.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível reativar.')
  },
})

function goEdit() {
  void router.push({ name: 'users-edit', params: { id: String(props.userId) } })
}

function goBack() {
  void router.push({ name: 'users' })
}

function displayRoles(roles: string[] = []) {
  return roles
    .filter((role) => role !== 'super-admin')
    .map((role) => (role === 'admin' ? 'Admin' : roleLabel(role)))
    .join(', ')
}

const permissionList = computed(() =>
  [...(user.value?.permissions ?? [])]
    .filter((name) => name !== 'clinics.manage')
    .sort((a, b) => permissionLabel(a).localeCompare(permissionLabel(b), 'pt-BR')),
)
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="user?.name ?? 'Usuário'">
      <template #actions>
        <Button variant="ghost" @click="goBack">Voltar</Button>
        <PermissionGate v-if="user?.is_active" permission="users.update">
          <Button variant="secondary" @click="goEdit">Editar</Button>
        </PermissionGate>
        <PermissionGate v-if="canDeactivate" permission="users.delete">
          <Button variant="destructive" @click="confirmOpen = true">Desativar</Button>
        </PermissionGate>
        <PermissionGate v-else-if="user && !user.is_active" permission="users.update">
          <Button variant="secondary" :loading="reactivating" @click="reactivate()">
            Reativar
          </Button>
        </PermissionGate>
      </template>
    </PageHeader>

    <Banner v-if="!auth.can('users.view')" variant="danger" title="Sem permissão">
      Você não pode ver este usuário.
    </Banner>

    <Banner v-else-if="isError" variant="danger" title="Não encontrado">
      Usuário indisponível nesta clínica.
    </Banner>

    <SurfaceCard v-else-if="isPending">
      <Skeleton class="h-6 w-1/2" />
      <Skeleton class="mt-4 h-4 w-full" />
      <Skeleton class="mt-2 h-4 w-3/4" />
    </SurfaceCard>

    <template v-else-if="user">
      <Banner v-if="!user.is_active" variant="warning" title="Inativo">
        Este usuário não acessa a clínica.
      </Banner>

      <SurfaceCard>
        <dl class="flex flex-col gap-4 text-[15px]">
          <div>
            <dt class="text-[13px] text-muted">E-mail</dt>
            <dd class="mt-0.5 text-title">{{ user.email }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Papéis</dt>
            <dd class="mt-0.5 text-title">{{ displayRoles(user.roles) || '—' }}</dd>
          </div>
          <div>
            <dt class="text-[13px] text-muted">Status</dt>
            <dd class="mt-0.5 text-title">{{ user.is_active ? 'Ativo' : 'Inativo' }}</dd>
          </div>
          <div v-if="permissionList.length">
            <dt class="text-[13px] text-muted">Permissões efetivas</dt>
            <dd class="mt-2">
              <ul class="grid list-disc grid-cols-1 gap-x-4 gap-y-1 pl-5 text-[13px] text-muted sm:grid-cols-2">
                <li v-for="permission in permissionList" :key="permission">
                  {{ permissionLabel(permission) }}
                </li>
              </ul>
            </dd>
          </div>
        </dl>
      </SurfaceCard>
    </template>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Desativar usuário?"
      description="O acesso à clínica será bloqueado."
      confirm-label="Desativar"
      :loading="deactivateMutation.isPending"
      @confirm="deactivateMutation.mutate()"
    />
  </div>
</template>
