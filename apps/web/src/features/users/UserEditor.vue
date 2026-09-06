<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Banner from '@/components/ui/Banner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import UserForm from '@/features/users/UserForm.vue'
import { createUser, getUser, updateUser } from '@/features/users/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { UserPayload } from '@/types/team-user'
import { ApiError } from '@/types/user'

const props = defineProps<{
  userId?: number
}>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const queryClient = useQueryClient()
const formRef = ref<{ setErrors: (errors: Record<string, string>) => void } | null>(null)

const isEdit = computed(() => Boolean(props.userId))
const requiredPermission = computed(() => (isEdit.value ? 'users.update' : 'users.create'))
const allowed = computed(() => auth.can(requiredPermission.value))

const {
  data: user,
  isPending: userPending,
  isError: userError,
} = useQuery({
  queryKey: ['users', computed(() => props.userId)],
  queryFn: () => getUser(props.userId as number),
  enabled: computed(() => Boolean(props.userId) && allowed.value),
})

const rolesLocked = computed(() => Boolean(user.value?.roles?.includes('admin')))

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: (payload: UserPayload) => {
    if (props.userId) {
      if (rolesLocked.value) {
        const { roles: _roles, ...rest } = payload
        return updateUser(props.userId, rest)
      }
      return updateUser(props.userId, payload)
    }
    return createUser(payload)
  },
  onSuccess: async (saved) => {
    toast.success(isEdit.value ? 'Usuário atualizado' : 'Usuário criado')
    await queryClient.invalidateQueries({ queryKey: ['users'] })
    await router.push({ name: 'users-show', params: { id: String(saved.id) } })
  },
  onError: (error) => {
    if (error instanceof ApiError && error.status === 422) {
      const mapped: Record<string, string> = {}
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[field] = messages[0] ?? error.message
      }
      formRef.value?.setErrors(mapped)
      if (!Object.keys(mapped).length) {
        toast.error(error.message)
      }
      return
    }
    if (error instanceof ApiError && error.status === 403) {
      toast.error('Sem permissão.')
      return
    }
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível salvar.')
  },
})

function onCancel() {
  if (props.userId) {
    void router.push({ name: 'users-show', params: { id: String(props.userId) } })
    return
  }
  void router.push({ name: 'users' })
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader :title="isEdit ? 'Editar usuário' : 'Novo usuário'" />

    <Banner v-if="!allowed" variant="danger" title="Sem permissão">
      Você não pode {{ isEdit ? 'editar' : 'criar' }} usuários.
    </Banner>

    <Banner v-else-if="isEdit && userError" variant="danger" title="Não encontrado">
      Usuário indisponível nesta clínica.
    </Banner>

    <SurfaceCard v-else-if="isEdit && userPending">
      <Skeleton class="h-10 w-2/3" />
      <Skeleton class="mt-4 h-10 w-full" />
      <Skeleton class="mt-3 h-10 w-full" />
    </SurfaceCard>

    <SurfaceCard v-else>
      <UserForm
        ref="formRef"
        :user="user"
        :loading="saving"
        :roles-locked="rolesLocked"
        :submit-label="isEdit ? 'Salvar' : 'Criar usuário'"
        @submit="save"
        @cancel="onCancel"
      />
    </SurfaceCard>
  </div>
</template>
