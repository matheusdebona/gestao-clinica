<script setup lang="ts">
import { useMutation, useQuery, useQueryClient } from "@tanstack/vue-query";
import { computed, nextTick, reactive, ref, watch } from "vue";
import AppDialog from "@/components/ui/AppDialog.vue";
import Button from "@/components/ui/Button.vue";
import FormField from "@/components/ui/FormField.vue";
import ListCard from "@/components/ui/ListCard.vue";
import MoneyInput from "@/components/ui/MoneyInput.vue";
import SectionTitle from "@/components/ui/SectionTitle.vue";
import Skeleton from "@/components/ui/Skeleton.vue";
import SurfaceCard from "@/components/ui/SurfaceCard.vue";
import {
    listGoalRemunerations,
    saveGoalRemuneration,
} from "@/features/finance/api";
import {
    GOAL_REMUNERATIONS_KEY,
    describeRemuneration,
    hasRemuneration,
    perOccurrenceLabel,
} from "@/features/finance/goalRemuneration";
import { emptyToMoney } from "@/lib/formatters";
import { useToastStore } from "@/stores/toast";
import type { GoalRemunerationRow } from "@/types/finance";
import { ApiError } from "@/types/user";

const props = defineProps<{ canManage: boolean }>();

const toast = useToastStore();
const queryClient = useQueryClient();

const query = useQuery({
    queryKey: GOAL_REMUNERATIONS_KEY,
    queryFn: listGoalRemunerations,
});
const rows = computed(() => query.data.value ?? []);

const COUNT_HINT: Record<GoalRemunerationRow["metric"], string> = {
    appointments:
        'Conta cada lead que a pessoa converteu em paciente com avaliação marcada (marco "Agendou") no mês. Se a avaliação for cancelada, deixa de contar.',
    approached:
        'Conta cada lead marcado como "Abordado" pela pessoa no mês, mesmo que o lead seja cancelado depois.',
    sales: "O bônus sai quando a soma das vendas confirmadas da pessoa no mês chega à meta. Comissão por venda fica nas regras acima.",
};

const dialogOpen = ref(false);
const editing = ref<GoalRemunerationRow | null>(null);
const form = reactive({ per: "", bonus: "" });
const errors = reactive<{ per?: string; bonus?: string }>({});

function openEdit(row: GoalRemunerationRow) {
    if (!props.canManage) return;
    editing.value = row;
    form.per =
        Number(row.per_occurrence_amount) > 0 ? row.per_occurrence_amount : "";
    form.bonus =
        Number(row.target_bonus_amount) > 0 ? row.target_bonus_amount : "";
    delete errors.per;
    delete errors.bonus;
    dialogOpen.value = true;
}

// No desktop, o foco vai para o primeiro valor (e não para o "i", que abriria a dica sobre o título).
watch(dialogOpen, async (open) => {
    if (!open || !window.matchMedia?.("(pointer: fine)").matches) return;
    await nextTick();
    setTimeout(() => {
        const id = editing.value?.supports_per_occurrence
            ? "gr-per"
            : "gr-bonus";
        document.getElementById(id)?.focus();
    }, 30);
});

const { mutate: save, isPending: saving } = useMutation({
    mutationFn: () => {
        const row = editing.value!;
        return saveGoalRemuneration(row.metric, {
            ...(row.supports_per_occurrence
                ? {
                      per_occurrence_amount:
                          emptyToMoney(String(form.per)) ?? "0",
                  }
                : {}),
            target_bonus_amount: emptyToMoney(String(form.bonus)) ?? "0",
        });
    },
    onSuccess: async (data) => {
        queryClient.setQueryData(GOAL_REMUNERATIONS_KEY, data);
        dialogOpen.value = false;
        await queryClient.invalidateQueries({
            queryKey: ["finance", "commissions"],
        });
        toast.success(
            "Remuneração salva. Vale para o mês atual; para meses anteriores, use Recalcular mês.",
        );
    },
    onError: (err) => {
        if (err instanceof ApiError) {
            errors.per = err.first("per_occurrence_amount") || undefined;
            errors.bonus =
                err.first("target_bonus_amount") ||
                (errors.per ? undefined : err.message);
        } else {
            errors.bonus = "Não foi possível salvar.";
        }
    },
});

function submit() {
    delete errors.per;
    delete errors.bonus;
    save();
}
</script>

<template>
    <SurfaceCard>
        <SectionTitle
            title="Remuneração por meta"
            description="Valor por ocorrência e bônus ao bater a meta do mês."
            hint="Para cada tipo de meta, defina quanto a pessoa ganha por ocorrência (ex.: R$ 80,00 por novo agendamento) e, se quiser, um bônus quando bater a meta do mês definida em Metas. Os ganhos entram nos lançamentos acima, na origem Metas. Mês civil de São Paulo. Lançamentos já pagos não mudam."
        />
        <div v-if="query.isPending.value" class="mt-4">
            <Skeleton class="h-12" />
            <Skeleton class="mt-3 h-12" />
        </div>
        <div v-else class="mt-2 divide-y divide-border-divider">
            <ListCard
                v-for="row in rows"
                :key="row.metric"
                :title="row.label"
                :description="describeRemuneration(row)"
                :badge="hasRemuneration(row) ? '' : 'Sem valor'"
                :show-chevron="canManage"
                @action="openEdit(row)"
            />
        </div>
    </SurfaceCard>

    <AppDialog
        v-model:open="dialogOpen"
        size="lg"
        :title="editing ? `Remuneração: ${editing.label}` : 'Remuneração'"
        description="Deixe em branco ou zero para não pagar."
    >
        <form
            v-if="editing"
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <FormField
                    v-if="editing.supports_per_occurrence"
                    :label="perOccurrenceLabel(editing)"
                    :hint="COUNT_HINT[editing.metric]"
                    tip-hint
                    :error="errors.per"
                    html-for="gr-per"
                >
                    <template #default="{ invalid }">
                        <MoneyInput
                            id="gr-per"
                            v-model="form.per"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <FormField
                    label="Bônus ao bater a meta"
                    :hint="
                        editing.supports_per_occurrence
                            ? 'Pago uma vez no mês quando a pessoa chega à meta definida em Metas (própria ou herdada do mês anterior). Sem meta, não há bônus.'
                            : COUNT_HINT[editing.metric]
                    "
                    tip-hint
                    :error="errors.bonus"
                    html-for="gr-bonus"
                >
                    <template #default="{ invalid }">
                        <MoneyInput
                            id="gr-bonus"
                            v-model="form.bonus"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
            </div>
            <p
                v-if="!editing.supports_per_occurrence"
                class="text-[13px] text-muted"
            >
                Comissão por venda continua nas regras de comissão.
            </p>
        </form>
        <template #footer-negative>
            <Button variant="ghost" type="button" @click="dialogOpen = false"
                >Cancelar</Button
            >
        </template>
        <template #footer>
            <Button type="button" :loading="saving" @click="submit"
                >Salvar</Button
            >
        </template>
    </AppDialog>
</template>
