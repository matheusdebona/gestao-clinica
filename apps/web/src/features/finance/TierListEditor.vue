<script setup lang="ts">
import Button from "@/components/ui/Button.vue";
import FormField from "@/components/ui/FormField.vue";
import Input from "@/components/ui/Input.vue";
import MoneyInput from "@/components/ui/MoneyInput.vue";
import {
    MAX_TIERS,
    emptyTierRow,
    tierPreview,
    type TierErrors,
    type TierFormRow,
    type TierKind,
} from "@/features/finance/remunerationTiers";

/**
 * Lista dinâmica de faixas (gatilho, valor fixo e %), a mesma para faturamento e agendamentos.
 * Desktop: dois campos por linha; mobile: um por linha.
 */
const props = defineProps<{
    kind: TierKind;
    errors?: TierErrors;
    idPrefix: string;
}>();

const rows = defineModel<TierFormRow[]>({ required: true });

const THRESHOLD_LABEL: Record<TierKind, string> = {
    appointments: "A partir de (agendamentos)",
    revenue: "A partir de (faturamento)",
};
const THRESHOLD_HINT: Record<TierKind, string> = {
    appointments:
        "Número de novos agendamentos da pessoa no mês, contados como em Metas (consulta cancelada não conta).",
    revenue:
        "Faturamento total da clínica no mês: vendas confirmadas (mesma receita da DRE), mês civil de São Paulo.",
};
const PERCENT_HINT: Record<TierKind, string> = {
    appointments:
        "Percentual sobre as vendas confirmadas no mês dos pacientes que a pessoa agendou.",
    revenue: "Percentual sobre o faturamento total do mês (não só o que passou do gatilho).",
};

function add() {
    if (rows.value.length >= MAX_TIERS) return;
    rows.value = [...rows.value, emptyTierRow()];
}

function remove(index: number) {
    rows.value = rows.value.filter((_, i) => i !== index);
}

function err(index: number, field: "threshold" | "fixed" | "percent"): string {
    return props.errors?.[`${index}.${field}`] ?? "";
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="rows.length === 0" class="text-[15px] text-muted">
            Nenhuma faixa. Adicione a primeira.
        </p>
        <fieldset
            v-for="(row, index) in rows"
            :key="row.key"
            class="glass-chip rounded-[14px] p-3.5"
            :aria-label="`Faixa ${index + 1}`"
        >
            <div class="mb-2 flex items-center justify-between gap-3">
                <span class="text-[15px] font-semibold text-title"
                    >Faixa {{ index + 1 }}</span
                >
                <Button
                    variant="ghost"
                    type="button"
                    :aria-label="`Remover faixa ${index + 1}`"
                    @click="remove(index)"
                    >Remover</Button
                >
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <FormField
                    :label="THRESHOLD_LABEL[kind]"
                    :hint="THRESHOLD_HINT[kind]"
                    tip-hint
                    :error="err(index, 'threshold')"
                    :html-for="`${idPrefix}-threshold-${index}`"
                >
                    <template #default="{ invalid }">
                        <MoneyInput
                            v-if="kind === 'revenue'"
                            :id="`${idPrefix}-threshold-${index}`"
                            v-model="row.threshold"
                            :invalid="invalid"
                        />
                        <Input
                            v-else
                            :id="`${idPrefix}-threshold-${index}`"
                            v-model="row.threshold"
                            type="text"
                            inputmode="numeric"
                            placeholder="Ex.: 10"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <FormField
                    label="Valor fixo"
                    :error="err(index, 'fixed')"
                    :html-for="`${idPrefix}-fixed-${index}`"
                >
                    <template #default="{ invalid }">
                        <MoneyInput
                            :id="`${idPrefix}-fixed-${index}`"
                            v-model="row.fixed"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <FormField
                    label="Percentual (%)"
                    :hint="PERCENT_HINT[kind]"
                    tip-hint
                    :error="err(index, 'percent')"
                    :html-for="`${idPrefix}-percent-${index}`"
                >
                    <template #default="{ invalid }">
                        <Input
                            :id="`${idPrefix}-percent-${index}`"
                            v-model="row.percent"
                            type="text"
                            inputmode="decimal"
                            placeholder="Ex.: 1"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <p
                    class="self-end pb-2.5 text-[13px] text-muted"
                    aria-live="polite"
                >
                    {{ tierPreview(row, kind) }}
                </p>
            </div>
        </fieldset>
        <div class="flex sm:justify-end">
            <Button
                variant="secondary"
                type="button"
                mobile-block
                :disabled="rows.length >= MAX_TIERS"
                @click="add"
                >Adicionar faixa</Button
            >
        </div>
    </div>
</template>
