<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { computed, ref } from "vue";
import AppDialog from "@/components/ui/AppDialog.vue";
import Button from "@/components/ui/Button.vue";
import FormField from "@/components/ui/FormField.vue";
import ListCard from "@/components/ui/ListCard.vue";
import SectionTitle from "@/components/ui/SectionTitle.vue";
import Select from "@/components/ui/Select.vue";
import Skeleton from "@/components/ui/Skeleton.vue";
import SurfaceCard from "@/components/ui/SurfaceCard.vue";
import TierListEditor from "@/features/finance/TierListEditor.vue";
import {
    listRevenueRemunerations,
    saveRevenueRemuneration,
} from "@/features/finance/api";
import { formatMonthLabel } from "@/features/finance/labels";
import {
    apiTierErrors,
    emptyTierRow,
    formToPayload,
    summarizeTiers,
    tiersToForm,
    validateTiers,
    type TierErrors,
    type TierFormRow,
} from "@/features/finance/remunerationTiers";
import { formatBRL } from "@/lib/formatters";
import { useToastStore } from "@/stores/toast";
import type { RevenueRemunerationRow } from "@/types/finance";
import { ApiError } from "@/types/user";

const props = defineProps<{ canManage: boolean }>();

const KEY = ["finance", "revenue-remunerations"] as const;
const toast = useToastStore();
const queryClient = useQueryClient();

const query = useQuery({ queryKey: KEY, queryFn: listRevenueRemunerations });
const rows = computed(() => query.data.value?.data ?? []);
const meta = computed(() => query.data.value?.meta);

const dialogOpen = ref(false);
const editing = ref<RevenueRemunerationRow | null>(null);
const person = ref("");
const tiers = ref<TierFormRow[]>([]);
const errors = ref<TierErrors>({});
const personError = ref("");
const formError = ref("");

const personOptions = computed(() => {
    const taken = new Set(rows.value.map((r) => r.user_id));
    return (meta.value?.users ?? [])
        .filter((u) => !taken.has(u.id))
        .map((u) => ({ value: String(u.id), label: u.name }));
});

function openNew() {
    if (!props.canManage) return;
    editing.value = null;
    person.value = "";
    tiers.value = [emptyTierRow()];
    errors.value = {};
    personError.value = "";
    formError.value = "";
    dialogOpen.value = true;
}

function openEdit(row: RevenueRemunerationRow) {
    if (!props.canManage) return;
    editing.value = row;
    person.value = String(row.user_id);
    tiers.value = tiersToForm(row.tiers);
    errors.value = {};
    personError.value = "";
    formError.value = "";
    dialogOpen.value = true;
}

const { mutate: save, isPending: saving } = useMutation({
    mutationFn: (payload: { userId: number; clear?: boolean }) =>
        saveRevenueRemuneration(
            payload.userId,
            payload.clear ? [] : formToPayload(tiers.value, "revenue"),
        ),
    onSuccess: async (data, payload) => {
        queryClient.setQueryData(KEY, data);
        dialogOpen.value = false;
        await queryClient.invalidateQueries({ queryKey: ["finance", "commissions"] });
        toast.success(
            payload.clear
                ? "Faixas removidas."
                : "Faixas salvas. Vale para o mês atual; para meses anteriores, use Recalcular mês.",
        );
    },
    onError: (err) => {
        if (err instanceof ApiError) {
            errors.value = apiTierErrors(err.errors);
            formError.value = Object.keys(errors.value).length ? "" : err.first("tiers") || err.message;
        } else {
            formError.value = "Não foi possível salvar.";
        }
    },
});

function submit() {
    personError.value = person.value ? "" : "Escolha a pessoa.";
    formError.value = tiers.value.length ? "" : "Adicione pelo menos uma faixa.";
    errors.value = validateTiers(tiers.value, "revenue");
    if (personError.value || formError.value || Object.keys(errors.value).length) return;
    save({ userId: Number(person.value) });
}

function clearTiers() {
    if (editing.value) save({ userId: editing.value.user_id, clear: true });
}
</script>

<template>
    <SurfaceCard>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <SectionTitle
                title="Remuneração por faturamento"
                :description="
                    meta
                        ? `Faturamento de ${formatMonthLabel(meta.month)} até agora: ${formatBRL(meta.month_revenue)}`
                        : 'Faixas pelo faturamento total da clínica no mês.'
                "
                hint="Para cada pessoa, defina faixas pelo faturamento total da clínica no mês (vendas confirmadas, a mesma receita da DRE; mês civil de São Paulo). Vale só a maior faixa atingida, com valor fixo, % do faturamento total ou os dois. Ex.: 70 mil → R$ 300; 80 mil → R$ 500; 100 mil → R$ 500 + 1%. Os ganhos entram nos lançamentos acima, na origem Faturamento, e na DRE. Lançamentos já pagos não mudam."
            />
            <Button v-if="canManage" variant="secondary" mobile-block @click="openNew"
                >Adicionar pessoa</Button
            >
        </div>
        <div v-if="query.isPending.value" class="mt-4">
            <Skeleton class="h-12" />
        </div>
        <p v-else-if="rows.length === 0" class="mt-4 text-[15px] text-muted">
            Ninguém recebe por faturamento.
        </p>
        <div v-else class="mt-2 divide-y divide-border-divider">
            <ListCard
                v-for="row in rows"
                :key="row.user_id"
                :title="row.user_name ?? 'Usuário'"
                :description="summarizeTiers(row.tiers, 'revenue')"
                :show-chevron="canManage"
                @action="openEdit(row)"
            />
        </div>
    </SurfaceCard>

    <AppDialog
        v-model:open="dialogOpen"
        size="lg"
        :title="editing ? `Faturamento: ${editing.user_name ?? 'Usuário'}` : 'Remuneração por faturamento'"
        description="Vale só a maior faixa atingida no mês."
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div v-if="!editing" class="grid gap-4 md:grid-cols-2">
                <FormField label="Pessoa" :error="personError" html-for="rr-person">
                    <template #default="{ invalid }">
                        <Select
                            id="rr-person"
                            v-model="person"
                            :options="personOptions"
                            placeholder="Escolha"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
            </div>
            <TierListEditor v-model="tiers" kind="revenue" id-prefix="rr" :errors="errors" />
            <p v-if="formError" class="text-[13px] text-danger" role="alert">{{ formError }}</p>
        </form>
        <template #footer-negative>
            <Button
                v-if="editing"
                variant="ghost"
                type="button"
                :loading="saving"
                @click="clearTiers"
                >Remover faixas</Button
            >
            <Button variant="ghost" type="button" @click="dialogOpen = false">Cancelar</Button>
        </template>
        <template #footer>
            <Button type="button" :loading="saving" @click="submit">Salvar</Button>
        </template>
    </AppDialog>
</template>
