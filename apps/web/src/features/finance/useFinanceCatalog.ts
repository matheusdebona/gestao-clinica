import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { listExpenseCategories } from '@/features/finance/api'
import { EXPENSE_KIND_LABELS, EXPENSE_KIND_ORDER } from '@/features/finance/labels'
import { useAuthStore } from '@/stores/auth'

/** Categorias, origens e campanhas para os formulários de despesa. */
export function useFinanceCatalog() {
  const auth = useAuthStore()
  const query = useQuery({
    queryKey: ['finance', 'expense-categories'],
    queryFn: listExpenseCategories,
    enabled: computed(() => auth.can('finance.view')),
  })

  const categories = computed(() => query.data.value?.data ?? [])
  const categoryOptions = computed(() =>
    [...categories.value]
      .filter((c) => c.is_active)
      .sort(
        (a, b) =>
          EXPENSE_KIND_ORDER.indexOf(a.kind) - EXPENSE_KIND_ORDER.indexOf(b.kind) ||
          a.name.localeCompare(b.name),
      )
      .map((c) => ({ value: String(c.id), label: `${EXPENSE_KIND_LABELS[c.kind]} · ${c.name}` })),
  )
  const originOptions = computed(() => [
    { value: 'none', label: 'Nenhuma' },
    ...(query.data.value?.meta.origins ?? []).map((o) => ({ value: String(o.id), label: o.name })),
  ])
  function campaignOptions(originId: string) {
    return [
      { value: 'none', label: 'Nenhuma' },
      ...(query.data.value?.meta.campaigns ?? [])
        .filter((c) => originId === 'none' || String(c.client_origin_id) === originId)
        .map((c) => ({ value: String(c.id), label: c.name })),
    ]
  }
  function kindOf(categoryId: string) {
    return categories.value.find((c) => String(c.id) === categoryId)?.kind ?? null
  }

  return { query, categories, categoryOptions, originOptions, campaignOptions, kindOf }
}
