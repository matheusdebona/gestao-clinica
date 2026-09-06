<script setup lang="ts">
import { useForm } from 'vee-validate'
import { computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import FormField from '@/components/ui/FormField.vue'
import Input from '@/components/ui/Input.vue'
import Switch from '@/components/ui/Switch.vue'
import { listAssignableRoles } from '@/features/users/api'
import {
  emptyUserForm,
  roleLabel,
  toUserPayload,
  userFormSchema,
  type UserFormValues,
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

const { defineField, handleSubmit, errors, setErrors, resetForm, setFieldValue } = useForm({
  validationSchema: computed(() => userFormSchema(isEdit.value)),
  initialValues: emptyUserForm(),
})

const [name, nameAttrs] = defineField('name')
const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')
const [isActive] = defineField('is_active')
const [roles] = defineField('roles')

const rolesQuery = useQuery({
  queryKey: ['roles', 'assignable'],
  queryFn: listAssignableRoles,
})

const assignableRoles = computed(() => rolesQuery.data.value ?? [])

watch(
  () => props.user,
  (user) => {
    if (!user) {
      resetForm({ values: emptyUserForm() })
      return
    }
    resetForm({
      values: {
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        is_active: user.is_active,
        roles: [...(user.roles ?? [])].filter((role) => role !== 'admin' && role !== 'super-admin'),
      } satisfies UserFormValues,
    })
  },
  { immediate: true },
)

function toggleRole(role: string, checked: boolean) {
  if (props.rolesLocked) {
    return
  }
  const current = new Set(roles.value ?? [])
  if (checked) {
    current.add(role)
  } else {
    current.delete(role)
  }
  setFieldValue('roles', [...current])
}

const onSubmit = handleSubmit((formValues) => {
  emit('submit', toUserPayload(formValues, isEdit.value))
})

defineExpose({
  setErrors: (fieldErrors: Record<string, string>) => setErrors(fieldErrors),
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
        <Input
          id="user-password"
          v-model="password"
          v-bind="passwordAttrs"
          type="password"
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
        <Input
          id="user-password-2"
          v-model="passwordConfirmation"
          v-bind="passwordConfirmationAttrs"
          type="password"
          autocomplete="new-password"
          :invalid="invalid"
        />
      </template>
    </FormField>

    <Switch v-model="isActive" label="Ativo" :disabled="rolesLocked" />

    <FormField label="Papéis" :error="errors.roles" hint="Pacotes de permissões da clínica.">
      <div class="flex flex-col gap-3 rounded-[10px] border border-border-subtle bg-canvas/60 px-3 py-3">
        <p v-if="rolesLocked" class="text-[13px] text-muted">
          O administrador da clínica não pode ter os papéis alterados.
        </p>
        <template v-else>
          <Checkbox
            v-for="role in assignableRoles"
            :id="`role-${role.name}`"
            :key="role.name"
            :model-value="(roles ?? []).includes(role.name)"
            :label="roleLabel(role.name)"
            @update:model-value="(checked) => toggleRole(role.name, checked)"
          />
          <p v-if="!assignableRoles.length && !rolesQuery.isPending" class="text-[13px] text-muted">
            Nenhum papel disponível.
          </p>
        </template>
      </div>
    </FormField>

    <div class="mt-2 flex gap-3">
      <Button type="button" variant="ghost" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" :loading="loading">{{ submitLabel ?? 'Salvar' }}</Button>
    </div>
  </form>
</template>
