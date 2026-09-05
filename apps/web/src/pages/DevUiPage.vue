<script setup lang="ts">
import { ref } from 'vue'
import { Bell, Settings } from '@lucide/vue'
import AppDialog from '@/components/ui/AppDialog.vue'
import Badge from '@/components/ui/Badge.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import ButtonAccent from '@/components/ui/ButtonAccent.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import ColorSwatch from '@/components/ui/ColorSwatch.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import FormField from '@/components/ui/FormField.vue'
import IconButton from '@/components/ui/IconButton.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import Input from '@/components/ui/Input.vue'
import ListCard from '@/components/ui/ListCard.vue'
import MaskedBox from '@/components/ui/MaskedBox.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SearchField from '@/components/ui/SearchField.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Spinner from '@/components/ui/Spinner.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Switch from '@/components/ui/Switch.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

const textValue = ref('Maria Silva')
const numberValue = ref('12')
const passwordValue = ref('senha123')
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

const selectOptions = [
  { value: 'protocolo-a', label: 'Protocolo A' },
  { value: 'protocolo-b', label: 'Protocolo B' },
  { value: 'protocolo-c', label: 'Protocolo C' },
]

const brandSwatches = [
  { name: 'primary', hex: '#5956A6' },
  { name: 'primaryDark', hex: '#4A4791' },
  { name: 'primaryLight', hex: '#ECEBFA' },
  { name: 'primaryHover', hex: '#6865B8' },
]

const bgSwatches = [
  { name: 'canvas', hex: '#EBF1F8' },
  { name: 'sidebar', hex: '#5956A6' },
  { name: 'surface', hex: '#FFFFFF' },
  { name: 'surfaceMuted', hex: '#F5F7FB' },
  { name: 'input', hex: '#F0F4F8' },
]

const textSwatches = [
  { name: 'title', hex: '#1F2430' },
  { name: 'body', hex: '#3A3F4D' },
  { name: 'muted', hex: '#7E8597' },
  { name: 'inverse', hex: '#FFFFFF' },
]

const accentSwatches = [
  { name: 'success', hex: '#10B981' },
  { name: 'successLight', hex: '#D1FAE5' },
  { name: 'danger', hex: '#EF4444' },
  { name: 'dangerLight', hex: '#FEE2E2' },
  { name: 'warning', hex: '#F59E0B' },
  { name: 'warningLight', hex: '#FEF3C7' },
  { name: 'cardPink', hex: '#FDF2F4' },
  { name: 'cardPurple', hex: '#F8F5FE' },
  { name: 'cardBlue', hex: '#EFF6FF' },
  { name: 'badgePurple', hex: '#A855F7' },
]

const borderSwatches = [
  { name: 'subtle', hex: '#E5E9F2' },
  { name: 'divider', hex: '#E2E8F0' },
]

function simulateLoading() {
  loadingPrimary.value = true
  window.setTimeout(() => {
    loadingPrimary.value = false
    toast.success('Ação concluída')
  }, 1200)
}
</script>

<template>
  <div class="min-h-screen bg-canvas">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-6">
      <PageHeader
        title="Design system"
        description="Modern Soft Violet — kitchen sink para validar tokens e componentes antes das features."
      >
        <template #actions>
          <IconButton label="Notificações">
            <Bell class="size-5" />
          </IconButton>
          <IconButton label="Configurações">
            <Settings class="size-5" />
          </IconButton>
        </template>
      </PageHeader>

      <SurfaceCard>
        <h2 class="mb-4">Cores — brand</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <ColorSwatch
            v-for="s in brandSwatches"
            :key="s.name"
            :name="s.name"
            :hex="s.hex"
          />
        </div>
      </SurfaceCard>

      <div class="grid gap-5 md:grid-cols-2">
        <SurfaceCard>
          <h2 class="mb-4">Backgrounds</h2>
          <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <ColorSwatch
              v-for="s in bgSwatches"
              :key="s.name"
              :name="s.name"
              :hex="s.hex"
            />
          </div>
        </SurfaceCard>
        <SurfaceCard>
          <h2 class="mb-4">Texto</h2>
          <div class="grid grid-cols-2 gap-4">
            <ColorSwatch
              v-for="s in textSwatches"
              :key="s.name"
              :name="s.name"
              :hex="s.hex"
            />
          </div>
        </SurfaceCard>
      </div>

      <SurfaceCard>
        <h2 class="mb-4">Accents / feedback</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
          <ColorSwatch
            v-for="s in accentSwatches"
            :key="s.name"
            :name="s.name"
            :hex="s.hex"
          />
        </div>
        <h3 class="mt-6 mb-3">Borders</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <ColorSwatch
            v-for="s in borderSwatches"
            :key="s.name"
            :name="s.name"
            :hex="s.hex"
          />
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <h2 class="mb-4">Tipografia</h2>
        <div class="space-y-3">
          <h1>H1 — título da página</h1>
          <h2>H2 — seção</h2>
          <h3>H3 — subtítulo</h3>
          <p class="text-body-copy text-body">
            Body — texto operacional da clínica (14px).
          </p>
          <p class="text-caption text-muted">
            Caption — dicas e metadados (12px).
          </p>
          <Badge variant="purple">Badge</Badge>
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <h2 class="mb-4">Botões</h2>
        <div class="flex flex-wrap items-center gap-3">
          <Button @click="simulateLoading">Primary</Button>
          <Button variant="secondary">Secondary</Button>
          <Button variant="ghost">Ghost</Button>
          <Button variant="destructive">Destructive</Button>
          <Button :loading="loadingPrimary" @click="simulateLoading">Loading</Button>
          <Button disabled>Disabled</Button>
          <ButtonAccent>CTA accent</ButtonAccent>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
          <Button @click="toast.success('Snack de sucesso')">Snack success</Button>
          <Button variant="destructive" @click="toast.error('Snack de erro')">
            Snack error
          </Button>
          <Button variant="secondary" @click="toast.info('Snack informativo')">
            Snack info
          </Button>
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <h2 class="mb-4">Busca</h2>
        <SearchField
          v-model="searchValue"
          placeholder="Buscar cliente, produto…"
          @search="toast.info(`Busca: ${searchValue || '(vazio)'}`)"
        />
      </SurfaceCard>

      <div class="grid gap-5 md:grid-cols-2">
        <SurfaceCard>
          <h2 class="mb-4">Inputs — default</h2>
          <div class="flex flex-col gap-4">
            <FormField label="Texto" hint="Nome completo" html-for="ok-text">
              <Input id="ok-text" v-model="textValue" type="text" />
            </FormField>
            <FormField label="Número" html-for="ok-number">
              <Input id="ok-number" v-model="numberValue" type="number" />
            </FormField>
            <FormField label="Senha" html-for="ok-password">
              <Input id="ok-password" v-model="passwordValue" type="password" />
            </FormField>
            <FormField label="E-mail" html-for="ok-email">
              <Input id="ok-email" v-model="emailValue" type="email" />
            </FormField>
            <FormField label="Data" html-for="ok-date">
              <Input id="ok-date" v-model="dateValue" type="date" />
            </FormField>
            <FormField label="Textarea" html-for="ok-notes">
              <Textarea id="ok-notes" v-model="notesValue" />
            </FormField>
            <FormField label="Select" html-for="ok-select">
              <Select id="ok-select" v-model="selectValue" :options="selectOptions" />
            </FormField>
            <Checkbox v-model="checked" label="Aceito os termos" />
            <Switch v-model="enabled" label="Notificações ativas" />
            <FormField label="Cartão mascarado">
              <MaskedBox value="**** **** **** 4242" />
            </FormField>
          </div>
        </SurfaceCard>

        <SurfaceCard>
          <h2 class="mb-4">Inputs — erro (422)</h2>
          <div class="flex flex-col gap-4">
            <FormField
              label="Texto"
              error="O campo nome é obrigatório."
              html-for="err-text"
            >
              <template #default="{ invalid }">
                <Input
                  id="err-text"
                  v-model="textValue"
                  type="text"
                  :invalid="invalid"
                />
              </template>
            </FormField>
            <FormField
              label="Número"
              error="Informe uma quantidade válida."
              html-for="err-number"
            >
              <template #default="{ invalid }">
                <Input
                  id="err-number"
                  v-model="numberValue"
                  type="number"
                  :invalid="invalid"
                />
              </template>
            </FormField>
            <FormField label="E-mail" error="E-mail inválido." html-for="err-email">
              <template #default="{ invalid }">
                <Input
                  id="err-email"
                  v-model="emailValue"
                  type="email"
                  :invalid="invalid"
                />
              </template>
            </FormField>
            <FormField label="Data" error="Data obrigatória." html-for="err-date">
              <template #default="{ invalid }">
                <Input
                  id="err-date"
                  v-model="dateValue"
                  type="date"
                  :invalid="invalid"
                />
              </template>
            </FormField>
            <FormField
              label="Textarea"
              error="Descreva o motivo em pelo menos 10 caracteres."
              html-for="err-notes"
            >
              <template #default="{ invalid }">
                <Textarea id="err-notes" v-model="notesValue" :invalid="invalid" />
              </template>
            </FormField>
            <FormField label="Select" error="Selecione um protocolo." html-for="err-select">
              <template #default="{ invalid }">
                <Select
                  id="err-select"
                  v-model="selectValue"
                  :options="selectOptions"
                  :invalid="invalid"
                />
              </template>
            </FormField>
            <InlineAlert variant="error">
              Corrija os campos destacados antes de continuar.
            </InlineAlert>
          </div>
        </SurfaceCard>
      </div>

      <SurfaceCard>
        <h2 class="mb-4">Feedback</h2>
        <div class="flex flex-col gap-3">
          <Banner variant="info" title="Informação">
            Estoque será recalculado ao concluir o atendimento.
          </Banner>
          <Banner variant="success" title="Sucesso">
            Orçamento confirmado com sucesso.
          </Banner>
          <Banner variant="warning" title="Atenção">
            Produto próximo do ponto de reposição.
          </Banner>
          <Banner variant="danger" title="Erro">
            Não foi possível concluir o atendimento.
          </Banner>
          <div class="flex flex-wrap items-center gap-2">
            <Badge variant="success">Concluído</Badge>
            <Badge variant="purple">Em andamento</Badge>
            <Badge variant="warning">Baixo estoque</Badge>
            <Badge variant="danger">Negativo</Badge>
            <Badge variant="muted">Rascunho</Badge>
            <Spinner size="sm" />
            <Spinner />
          </div>
          <div class="grid gap-2 sm:grid-cols-3">
            <Skeleton class="h-10" />
            <Skeleton class="h-10" />
            <Skeleton class="h-10" />
          </div>
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <h2 class="mb-4">Cards de lista</h2>
        <div class="flex flex-col gap-3">
          <ListCard
            variant="blue"
            title="Consulta de retorno"
            meta="Hoje · 14:30 · Dra. Ana"
            badge="Agendado"
            badge-variant="purple"
            @action="toast.info('Abrir consulta')"
          />
          <ListCard
            variant="purple"
            title="Protocolo facial"
            meta="Sessão 2 de 4"
            badge="Em curso"
            badge-variant="purple"
            @action="toast.info('Abrir protocolo')"
          />
          <ListCard
            variant="pink"
            title="Avaliação inicial"
            meta="Concluída ontem"
            badge="OK"
            badge-variant="success"
            @action="toast.info('Abrir avaliação')"
          />
        </div>
      </SurfaceCard>

      <SurfaceCard>
        <h2 class="mb-4">Overlays</h2>
        <div class="flex flex-wrap gap-3">
          <Button @click="dialogOpen = true">Abrir dialog</Button>
          <Button variant="destructive" @click="confirmOpen = true">
            Abrir confirmação
          </Button>
        </div>
      </SurfaceCard>
    </div>

    <AppDialog
      v-model:open="dialogOpen"
      title="Detalhe do componente"
      description="Dialog Soft Violet para conteúdo curto em desktop."
    >
      <p>Use sheets no phone e dialogs no md+ conforme a spec.</p>
    </AppDialog>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Excluir registro?"
      description="Esta ação não pode ser desfeita."
      confirm-label="Excluir"
      @confirm="toast.success('Registro excluído (demo)')"
    />
  </div>
</template>

<style scoped>
.text-caption {
  font-size: var(--sv-text-caption);
  line-height: var(--sv-leading-caption);
}
.text-body-copy {
  font-size: var(--sv-text-body);
  line-height: var(--sv-leading-body);
}
</style>
