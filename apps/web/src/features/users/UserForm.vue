<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, ref, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import PasswordInput from '@/components/ui/PasswordInput.vue'
import Switch from '@/components/ui/Switch.vue'
import { listAssignableRoles } from '@/features/users/api'
import {
  emptyUserForm,
  permissionLabel,
  roleLabel,
  roleSummary,
  toUserPayload,
  userFormSchema,
} from '@/features/users/schema'
import type { ClinicUser, UserPayload } from '@/types/team-user'

const props = defineProps<{
  user?: ClinicUser | null
  submitLabel?: string
  loading?: boolean
  rolesLocked?: boolean
}>()

const emit = defineEmits<{
  submit: [payload: UserPayload]
  cancel: []
}>()

const isEdit = computed(() => Boolean(props.user))
const selectedRoles = ref<string[]>([])
const rolesError = ref('')
const expandedRole = ref<string | null>(null)
const isActive = ref(true)

const { defineField, handleSubmit, errors, setErrors, resetForm } = useForm({
  validationSchema: computed(() => userFormSchema(isEdit.value)),
  initialValues: emptyUserForm(),
})

const [name, nameAttrs] = defineField('name')
const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')

const rolesQuery = useQuery({
  queryKey: ['roles', 'assignable'],
  queryFn: listAssignableRoles,
})

const assignableRoles = computed(() => rolesQuery.data.value ?? [])

watch(
  () => props.user,
  (user) => {
    rolesError.value = ''
    if (!user) {
      selectedRoles.value = []
      isActive.value = true
      resetForm({ values: emptyUserForm() })
      return
    }
    const roles = [...(user.roles ?? [])].filter(
      (role) => role !== 'admin' && role !== 'super-admin',
    )
    selectedRoles.value = roles
    isActive.value = user.is_active
    resetForm({
      values: {
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        is_active: user.is_active,
      },
    })
  },
  { immediate: true },
)

function toggleRole(role: string, checked: boolean) {
  if (props.rolesLocked) {
    return
  }
  const current = new Set(selectedRoles.value)
  if (checked) {
    current.add(role)
  } else {
    current.delete(role)
  }
  selectedRoles.value = [...current]
  if (selectedRoles.value.length > 0) {
    rolesError.value = ''
  }
}

function toggleExpanded(role: string) {
  expandedRole.value = expandedRole.value === role ? null : role
}

const submitForm = handleSubmit((formValues) => {
  if (!props.rolesLocked && selectedRoles.value.length < 1) {
    rolesError.value = 'Selecione ao menos um papel.'
    return
  }

  emit(
    'submit',
    toUserPayload(
      {
        ...formValues,
        is_active: isActive.value,
        roles: [...selectedRoles.value],
      },
      isEdit.value,
    ),
  )
})

function onSubmit() {
  rolesError.value = ''
  void submitForm()
}

defineExpose({
  setErrors: (fieldErrors: Record<string, string>) => {
    if (fieldErrors.roles) {
      rolesError.value = fieldErrors.roles
    }
    const { roles: _roles, ...rest } = fieldErrors
    setErrors(rest)
  },
})
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <FormField label="Nome" :error="errors.name" html-for="user-name">
      <template #default="{ invalid }">
        <Input id="user-name" v-model="name" v-bind="nameAttrs" type="text" :invalid="invalid" />
      </template>
    </FormField>

    <FormField label="E-mail" :error="errors.email" html-for="user-email">
      <template #default="{ invalid }">
        <Input id="user-email" v-model="email" v-bind="emailAttrs" type="email" :invalid="invalid" />
      </template>
    </FormField>

    <FormField
      label="Senha"
      :hint="isEdit ? 'Deixe em branco para manter a senha atual.' : 'Mínimo 10 caracteres, com maiúscula, número e símbolo.'"
      :error="errors.password"
      html-for="user-password"
    >
      <template #default="{ invalid }">
        <PasswordInput
          id="user-password"
          v-model="password"
          v-bind="passwordAttrs"
          autocomplete="new-password"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <FormField
      label="Confirmar senha"
      :error="errors.password_confirmation"
      html-for="user-password-2"
    >
      <template #default="{ invalid }">
        <PasswordInput
          id="user-password-2"
          v-model="passwordConfirmation"
          v-bind="passwordConfirmationAttrs"
          autocomplete="new-password"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <Switch v-model="isActive" label="Ativo" :disabled="rolesLocked" />

    <FormField
      label="Papéis"
      :error="rolesError"
      hint="Cada papel é um pacote de permissões. O e-mail precisa ser único na plataforma."
    >
      <div class="glass-clear flex flex-col gap-3 rounded-[12px] px-3 py-3">
        <p v-if="rolesLocked" class="text-[13px] text-muted">
          O administrador da clínica não pode ter os papéis alterados.
        </p>
        <template v-else>
          <div
            v-for="role in assignableRoles"
            :key="role.name"
            class="flex flex-col gap-2 border-b border-border-subtle pb-3 last:border-b-0 last:pb-0"
          >
            <div class="flex items-start justify-between gap-3">
              <Checkbox
                :id="`role-${role.name}`"
                class="min-w-0 flex-1"
                :model-value="selectedRoles.includes(role.name)"
                @update:model-value="(checked) => toggleRole(role.name, checked)"
              >
                <span class="flex flex-col gap-0.5">
                  <span class="font-medium text-title">{{ roleLabel(role.name) }}</span>
                  <span class="text-[13px] font-normal text-muted">{{ roleSummary(role.name) }}</span>
                </span>
              </Checkbox>
              <Button
                type="button"
                variant="ghost"
                class="!h-auto shrink-0 !px-1 !py-0"
                @click="toggleExpanded(role.name)"
              >
                {{ expandedRole === role.name ? 'Ocultar permissões' : 'Ver permissões' }}
              </Button>
            </div>
            <ul
              v-if="expandedRole === role.name"
              class="grid list-disc grid-cols-1 gap-x-4 gap-y-1 pl-5 text-[13px] text-muted sm:grid-cols-2"
            >
              <li v-for="permission in role.permissions" :key="permission">
                {{ permissionLabel(permission) }}
              </li>
            </ul>
          </div>
          <p v-if="!assignableRoles.length && !rolesQuery.isPending" class="text-[13px] text-muted">
            Nenhum papel disponível.
          </p>
        </template>
      </div>
    </FormField>

    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button type="button" variant="ghost" :disabled="loading" @click="emit('cancel')">
        Cancelar
      </Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
