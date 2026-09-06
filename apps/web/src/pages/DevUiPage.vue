<script setup lang="ts">
import { ref } from 'vue'
import ClientSearchBar from '@/components/patterns/ClientSearchBar.vue'
import MoneyDisplay from '@/components/patterns/MoneyDisplay.vue'
import StockStatusBadge from '@/components/patterns/StockStatusBadge.vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ButtonAccent from '@/components/ui/ButtonAccent.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import ColorSwatch from '@/components/ui/ColorSwatch.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormField from '@/components/ui/FormField.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import MaskedBox from '@/components/ui/MaskedBox.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import PasswordInput from '@/components/ui/PasswordInput.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Spinner from '@/components/ui/Spinner.vue'
import SidebarNavItem from '@/components/ui/SidebarNavItem.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

const textValue = ref('Maria Silva')
const numberValue = ref('12')
const passwordValue = ref('senha123')
const passwordConfirmValue = ref('senha123')
const emailValue = ref('maria@clinica.test')
const dateValue = ref('2026-09-05')
const notesValue = ref('Observações da consulta')
const selectValue = ref('protocolo-a')
const searchValue = ref('')
const checked = ref(true)
const enabled = ref(false)
const loadingPrimary = ref(false)

const dialogOpen = ref(false)
const confirmOpen = ref(false)
const page = ref(2)

const selectOptions = [
  { value: 'protocolo-a', label: 'Protocolo A' },
  { value: 'protocolo-b', label: 'Protocolo B' },
  { value: 'protocolo-c', label: 'Protocolo C' },
]

const palette = [
  { name: 'Brand', hex: '#5956A6' },
  { name: 'Canvas', hex: '#F2F2F7' },
  { name: 'Surface', hex: '#FFFFFF' },
  { name: 'Title', hex: '#1C1C1E' },
  { name: 'Muted', hex: '#8E8E93' },
  { name: 'Success', hex: '#34C759' },
  { name: 'Warning', hex: '#FF9F0A' },
  { name: 'Danger', hex: '#FF3B30' },
]

function simulateLoading() {
  loadingPrimary.value = true
  window.setTimeout(() => {
    loadingPrimary.value = false
    toast.success('Concluído')
  }, 1100)
}
</script>

<template>
  <div class="min-h-screen bg-canvas">
    <div class="mx-auto flex w-full max-w-[720px] flex-col gap-8 px-5 py-10 md:px-8">
      <PageHeader
        title="Componentes"
        description="Violeta como acento. O resto é silêncio — para validar antes das telas da clínica."
      />

      <section class="flex flex-col gap-2">
        <p class="section-label">Aparência</p>
        <SurfaceCard>
          <div class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
            <ColorSwatch
              v-for="s in palette"
              :key="s.name"
              :name="s.name"
              :hex="s.hex"
            />
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Tipo</p>
        <SurfaceCard>
          <div class="space-y-3">
            <h1>Título grande</h1>
            <h2>Seção</h2>
            <p class="text-[15px] text-body">
              Texto operacional. Inter, peso médio, tracking fechado.
            </p>
            <p class="text-[13px] text-muted">Meta e dicas ficam neste tom.</p>
            <div class="flex flex-wrap gap-2">
              <Badge>Rascunho</Badge>
              <Badge variant="purple">Em curso</Badge>
              <Badge variant="success">Concluído</Badge>
              <Badge variant="warning">Estoque</Badge>
              <Badge variant="danger">Atraso</Badge>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <MoneyDisplay value="120.5" />
              <StockStatusBadge level="ok" />
              <StockStatusBadge level="low" />
              <StockStatusBadge level="reorder" />
              <StockStatusBadge level="negative" />
            </div>
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Ações</p>
        <SurfaceCard>
          <div class="flex flex-wrap items-center gap-2">
            <Button @click="simulateLoading">Continuar</Button>
            <Button variant="secondary">Secundário</Button>
            <Button variant="ghost">Texto</Button>
            <Button variant="destructive">Remover</Button>
            <Button :loading="loadingPrimary" @click="simulateLoading">Salvar</Button>
            <Button disabled>Indisponível</Button>
            <ButtonAccent>Contorno</ButtonAccent>
          </div>
          <div class="mt-5 flex flex-wrap gap-2">
            <Button variant="secondary" @click="toast.success('Salvo')">Aviso de sucesso</Button>
            <Button variant="secondary" @click="toast.error('Não foi possível salvar')">
              Aviso de erro
            </Button>
            <Button variant="secondary" @click="toast.info('Sessão em andamento')">
              Aviso
            </Button>
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Busca</p>
        <SurfaceCard>
          <SearchField
            v-model="searchValue"
            placeholder="Pacientes, produtos"
            @search="toast.info(searchValue || 'Nada para buscar')"
          />
          <div class="mt-4">
            <ClientSearchBar
              v-model="searchValue"
              @search="toast.info(searchValue || 'Nada para buscar')"
            />
          </div>
        </SurfaceCard>
      </section>

      <section class="grid gap-8 md:grid-cols-2 md:gap-5">
        <div class="flex flex-col gap-2">
          <p class="section-label">Campos</p>
          <SurfaceCard>
            <div class="flex flex-col gap-4">
              <FormField label="Nome" hint="Como aparece na agenda" html-for="ok-text">
                <Input id="ok-text" v-model="textValue" type="text" />
              </FormField>
              <FormField label="Quantidade" html-for="ok-number">
                <Input id="ok-number" v-model="numberValue" type="number" />
              </FormField>
              <FormField label="Senha" html-for="ok-password">
                <PasswordInput id="ok-password" v-model="passwordValue" />
              </FormField>
              <FormField label="Confirmar senha" html-for="ok-password-2">
                <PasswordInput
                  id="ok-password-2"
                  v-model="passwordConfirmValue"
                  autocomplete="new-password"
                />
              </FormField>
              <FormField label="Senha (desabilitada)" html-for="ok-password-disabled">
                <PasswordInput id="ok-password-disabled" model-value="••••••••" disabled />
              </FormField>
              <FormField label="E-mail" html-for="ok-email">
                <Input id="ok-email" v-model="emailValue" type="email" />
              </FormField>
              <FormField label="Data" html-for="ok-date">
                <Input id="ok-date" v-model="dateValue" type="date" />
              </FormField>
              <FormField label="Notas" html-for="ok-notes">
                <Textarea id="ok-notes" v-model="notesValue" />
              </FormField>
              <FormField label="Protocolo" html-for="ok-select">
                <Select id="ok-select" v-model="selectValue" :options="selectOptions" />
              </FormField>
              <div class="border-t border-border-divider pt-3">
                <Checkbox v-model="checked" label="Lembrar neste dispositivo" />
              </div>
              <div class="border-t border-border-divider pt-3">
                <Switch v-model="enabled" label="Notificações" />
              </div>
              <FormField label="Cartão">
                <MaskedBox value="•••• 4242" />
              </FormField>
            </div>
          </SurfaceCard>
        </div>

        <div class="flex flex-col gap-2">
          <p class="section-label">Erros</p>
          <SurfaceCard>
            <div class="flex flex-col gap-4">
              <FormField label="Nome" error="Informe o nome." html-for="err-text">
                <template #default="{ invalid }">
                  <Input id="err-text" v-model="textValue" type="text" :invalid="invalid" />
                </template>
              </FormField>
              <FormField label="Quantidade" error="Número inválido." html-for="err-number">
                <template #default="{ invalid }">
                  <Input id="err-number" v-model="numberValue" type="number" :invalid="invalid" />
                </template>
              </FormField>
              <FormField label="Senha" error="Senha obrigatória." html-for="err-password">
                <template #default="{ invalid }">
                  <PasswordInput id="err-password" v-model="passwordValue" :invalid="invalid" />
                </template>
              </FormField>
              <FormField label="E-mail" error="E-mail inválido." html-for="err-email">
                <template #default="{ invalid }">
                  <Input id="err-email" v-model="emailValue" type="email" :invalid="invalid" />
                </template>
              </FormField>
              <FormField label="Data" error="Escolha uma data." html-for="err-date">
                <template #default="{ invalid }">
                  <Input id="err-date" v-model="dateValue" type="date" :invalid="invalid" />
                </template>
              </FormField>
              <FormField label="Notas" error="Mínimo de 10 caracteres." html-for="err-notes">
                <template #default="{ invalid }">
                  <Textarea id="err-notes" v-model="notesValue" :invalid="invalid" />
                </template>
              </FormField>
              <FormField label="Protocolo" error="Selecione um protocolo." html-for="err-select">
                <template #default="{ invalid }">
                  <Select
                    id="err-select"
                    v-model="selectValue"
                    :options="selectOptions"
                    :invalid="invalid"
                  />
                </template>
              </FormField>
              <InlineAlert>Corrija os campos acima para continuar.</InlineAlert>
            </div>
          </SurfaceCard>
        </div>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Feedback</p>
        <SurfaceCard>
          <div class="flex flex-col gap-3">
            <Banner variant="info" title="Estoque">
              Recalcula ao concluir o atendimento.
            </Banner>
            <Banner variant="success" title="Pronto">
              Orçamento confirmado.
            </Banner>
            <Banner variant="warning" title="Atenção">
              Produto perto do ponto de reposição.
            </Banner>
            <Banner variant="danger" title="Falha">
              Não foi possível concluir.
            </Banner>
            <div class="flex items-center gap-3 pt-1">
              <Spinner size="sm" />
              <Spinner />
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
              <Skeleton class="h-9" />
              <Skeleton class="h-9" />
              <Skeleton class="h-9" />
            </div>
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Lista</p>
        <SurfaceCard>
          <div class="divide-y divide-border-divider">
            <ListCard
              title="Retorno"
              meta="Hoje, 14:30 · Ana"
              badge="Agenda"
              badge-variant="purple"
              @action="toast.info('Retorno')"
            />
            <ListCard
              title="Protocolo facial"
              meta="Sessão 2 de 4"
              badge="Em curso"
              badge-variant="muted"
              @action="toast.info('Protocolo')"
            />
            <ListCard
              title="Avaliação"
              meta="Ontem"
              badge="Concluído"
              badge-variant="success"
              @action="toast.info('Avaliação')"
            />
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Navegação</p>
        <SurfaceCard class="!bg-sidebar">
          <div class="flex flex-col gap-0.5">
            <SidebarNavItem label="Início" active />
            <SidebarNavItem label="Clientes" />
            <SidebarNavItem label="Sair" disabled />
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Vazio e páginas</p>
        <SurfaceCard :padding="false">
          <EmptyState
            title="Nenhum cliente ainda"
            description="Cadastre o primeiro paciente da clínica."
          >
            <template #action>
              <Button @click="toast.info('Novo')">Novo cliente</Button>
            </template>
          </EmptyState>
        </SurfaceCard>
        <SurfaceCard>
          <Pagination :page="page" :last-page="3" @update:page="page = $event" />
          <div class="mt-4 border-t border-border-divider pt-4">
            <Pagination :page="1" :last-page="1" disabled />
          </div>
        </SurfaceCard>
      </section>

      <section class="flex flex-col gap-2">
        <p class="section-label">Janelas</p>
        <SurfaceCard>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" @click="dialogOpen = true">Detalhe</Button>
            <Button variant="destructive" @click="confirmOpen = true">Excluir</Button>
          </div>
        </SurfaceCard>
      </section>
    </div>

    <AppDialog
      v-model:open="dialogOpen"
      title="Detalhe"
      description="Painel curto. No telefone, preferir sheet."
    >
      <p>Conteúdo secundário fica aqui, sem decoração extra.</p>
    </AppDialog>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Excluir este registro?"
      description="Não dá para desfazer."
      confirm-label="Excluir"
      @confirm="toast.success('Excluído')"
    />
  </div>
</template>

<style scoped>
.section-label {
  padding-inline: 4px;
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.01em;
  color: var(--sv-text-muted);
}
</style>
