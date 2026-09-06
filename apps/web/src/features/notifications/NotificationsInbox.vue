<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import NotificationInboxItem from '@/components/patterns/NotificationInboxItem.vue'
import Banner from '@/components/ui/Banner.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import SurfaceCard from '@/components/ui/SurfaceCard.vue'
import Tabs from '@/components/ui/Tabs.vue'
import {
  getUnreadNotificationCount,
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '@/features/notifications/api'
import {
  isNotificationUnread,
  notificationHref,
  notificationMessage,
  notificationTitle,
} from '@/features/notifications/links'
import { formatRelativeTime } from '@/lib/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { ApiError } from '@/types/user'
import type { ClinicNotification, NotificationInboxFilter } from '@/types/notification'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const queryClient = useQueryClient()

const page = ref(1)
const filter = ref<NotificationInboxFilter>('all')

const filterItems = [
  { value: 'all', label: 'Todas' },
  { value: 'unread', label: 'Não lidas' },
  { value: 'stock', label: 'Estoque' },
  { value: 'agenda', label: 'Agenda' },
]

watch(filter, () => {
  page.value = 1
})

const canView = computed(() => auth.can('products.view'))

const listParams = computed(() => ({
  page: page.value,
  unread: filter.value === 'unread' ? true : undefined,
  category: filter.value === 'stock' || filter.value === 'agenda' ? filter.value : undefined,
}))

const {
  data: listData,
  isPending,
  isError,
  isFetching,
} = useQuery({
  queryKey: ['notifications', 'list', listParams],
  queryFn: () => listNotifications(listParams.value),
  enabled: canView,
})

const { data: unreadCount } = useQuery({
  queryKey: ['notifications', 'unread-count'],
  queryFn: getUnreadNotificationCount,
  enabled: canView,
})

const notifications = computed(() => listData.value?.data ?? [])
const lastPage = computed(() => listData.value?.meta.last_page ?? 1)
const total = computed(() => listData.value?.meta.total ?? 0)
const hasUnread = computed(() => (unreadCount.value ?? 0) > 0)

const emptyDescription = computed(() => {
  if (filter.value === 'stock') {
    return 'Nenhum alerta de estoque nesta lista.'
  }
  if (filter.value === 'agenda') {
    return 'Nenhum alerta da agenda nesta lista.'
  }
  return 'Você está em dia'
})

async function invalidateInbox() {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: ['notifications', 'list'] }),
    queryClient.invalidateQueries({ queryKey: ['notifications', 'unread-count'] }),
  ])
}

const { mutate: markAll, isPending: markingAll } = useMutation({
  mutationFn: markAllNotificationsRead,
  async onSuccess() {
    await invalidateInbox()
    toast.success('Alertas marcados como lidos.')
  },
  onError(error) {
    toast.error(error instanceof ApiError ? error.message : 'Não foi possível marcar os alertas.')
  },
})

async function openNotification(item: ClinicNotification) {
  if (isNotificationUnread(item)) {
    try {
      await markNotificationRead(item.id)
      await invalidateInbox()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Não foi possível marcar o alerta.')
    }
  }

  const href = notificationHref(item)
  if (href) {
    await router.push(href)
  }
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-[720px] flex-col gap-6">
    <PageHeader
      title="Alertas"
      :description="total ? `${total} nesta lista` : undefined"
    >
      <template #actions>
        <Button
          v-if="canView"
          variant="ghost"
          :disabled="!hasUnread || markingAll"
          :loading="markingAll"
          @click="markAll()"
        >
          Marcar todas como lidas
        </Button>
      </template>
    </PageHeader>

    <Banner v-if="!canView" variant="danger" title="Sem permissão">
      Você não pode ver os alertas de estoque.
    </Banner>

    <template v-else>
      <Tabs v-model="filter" :items="filterItems" block />

      <Banner v-if="isError" variant="danger" title="Não foi possível carregar">
        Tente de novo em instantes.
      </Banner>

      <SurfaceCard v-else-if="isPending" :padding="false">
        <div class="flex flex-col gap-3 p-5">
          <Skeleton class="h-14" />
          <Skeleton class="h-14" />
          <Skeleton class="h-14" />
        </div>
      </SurfaceCard>

      <SurfaceCard v-else-if="notifications.length === 0" :padding="false">
        <EmptyState title="Nenhum alerta" :description="emptyDescription" />
      </SurfaceCard>

      <SurfaceCard v-else :padding="false">
        <div class="divide-y divide-border-divider px-5 py-2">
          <NotificationInboxItem
            v-for="item in notifications"
            :key="item.id"
            :title="notificationTitle(item)"
            :message="notificationMessage(item)"
            :relative-time="formatRelativeTime(item.created_at)"
            :unread="isNotificationUnread(item)"
            :navigable="Boolean(notificationHref(item))"
            @action="openNotification(item)"
          />
        </div>
      </SurfaceCard>

      <Pagination
        v-if="lastPage > 1"
        :page="page"
        :last-page="lastPage"
        :disabled="isFetching"
        @update:page="page = $event"
      />
    </template>
  </div>
</template>
