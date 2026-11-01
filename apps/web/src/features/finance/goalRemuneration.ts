import {
    summarizeTiers,
    thresholdLabel,
} from "@/features/finance/remunerationTiers";
import { formatBRL, formatPercent } from "@/lib/formatters";
import type {
    CommissionByOrigin,
    CommissionEntryRow,
    CommissionOrigin,
    GoalMetric,
    GoalRemunerationRow,
} from "@/types/finance";

export const GOAL_REMUNERATIONS_KEY = [
    "finance",
    "goal-remunerations",
] as const;

export const ORIGIN_LABELS: Record<CommissionOrigin, string> = {
    sales: "Vendas",
    sessions: "Sessões",
    goals: "Metas",
    revenue: "Faturamento",
};

const OCCURRENCE_LABELS: Record<GoalMetric, string> = {
    appointments: "Novo agendamento",
    approached: "Lead abordado",
    sales: "Venda",
};

const BONUS_LABELS: Record<GoalMetric, string> = {
    appointments: "Bônus: meta de agendamentos",
    approached: "Bônus: meta de leads abordados",
    sales: "Bônus: meta de vendas",
};

/** Valor por ocorrência rotulado pela unidade, ex.: "Valor por agendamento". */
export function perOccurrenceLabel(
    row: Pick<GoalRemunerationRow, "unit_label">,
): string {
    return row.unit_label ? `Valor ${row.unit_label}` : "Valor por ocorrência";
}

function positive(value: string | null | undefined): boolean {
    return Number(value ?? 0) > 0;
}

/** "R$ 80,00 por agendamento · bônus de R$ 300,00 ao bater a meta · 2 faixas…" ou "Sem remuneração". */
export function describeRemuneration(row: GoalRemunerationRow): string {
    const parts: string[] = [];
    if (row.supports_per_occurrence && positive(row.per_occurrence_amount)) {
        parts.push(
            `${formatBRL(row.per_occurrence_amount)} ${row.unit_label ?? "por ocorrência"}`,
        );
    }
    if (positive(row.target_bonus_amount)) {
        const bonus = `${formatBRL(row.target_bonus_amount)} ao bater a meta`;
        parts.push(parts.length ? `bônus de ${bonus}` : `Bônus de ${bonus}`);
    }
    if (row.supports_tiers && row.tiers?.length) {
        const tiers = summarizeTiers(row.tiers, "appointments");
        parts.push(parts.length ? tiers.charAt(0).toLowerCase() + tiers.slice(1) : tiers);
    }
    return parts.length ? parts.join(" · ") : "Sem remuneração";
}

export function hasRemuneration(row: GoalRemunerationRow): boolean {
    return (
        (row.supports_per_occurrence && positive(row.per_occurrence_amount)) ||
        positive(row.target_bonus_amount) ||
        Boolean(row.supports_tiers && row.tiers?.length)
    );
}

/** Primeira linha de um lançamento no extrato da pessoa. */
export function entryTitle(entry: CommissionEntryRow): string {
    if (entry.origin === "revenue") return "Faturamento da clínica";
    if (entry.origin === "goals" && entry.goal_metric) {
        if (entry.goal_kind === "bonus") return BONUS_LABELS[entry.goal_metric];
        if (entry.goal_kind === "tier") return "Faixa de agendamentos";
        return `${OCCURRENCE_LABELS[entry.goal_metric]} · ${entry.client_name ?? "—"}`;
    }
    return `${entry.origin === "sessions" ? "Sessão" : "Venda"} · ${entry.client_name ?? "—"}`;
}

/** Segunda linha: protocolo (vendas/sessões) ou o tipo de meta. */
export function entrySubtitle(entry: CommissionEntryRow, date: string): string {
    if (entry.goal_kind === "tier") {
        const kind = entry.origin === "revenue" ? "revenue" : "appointments";
        const parts = [`Faixa: ${thresholdLabel(entry.tier_threshold ?? 0, kind)}`];
        if (entry.origin === "revenue" && !positive(entry.rate_percent)) {
            parts.push(`faturamento ${formatBRL(entry.base_amount)}`);
        }
        if (positive(entry.rate_percent)) {
            parts.push(
                `${formatPercent(entry.rate_percent)} de ${formatBRL(entry.base_amount)}`,
            );
        }
        return parts.join(" · ");
    }
    if (entry.origin === "goals") {
        if (entry.goal_kind === "bonus") {
            const target =
                entry.goal_metric === "sales"
                    ? formatBRL(entry.base_amount)
                    : String(Math.round(Number(entry.base_amount)));
            return `Meta do mês: ${target}`;
        }
        return `Meta · ${date}`;
    }
    return `${entry.protocol_name ?? "Geral"} · ${date}`;
}

/** Origens com valor, na ordem Vendas, Sessões, Metas, Faturamento. */
export function originBreakdown(
    byOrigin: CommissionByOrigin | undefined,
): { key: CommissionOrigin; label: string; amount: string }[] {
    if (!byOrigin) return [];
    return (Object.keys(ORIGIN_LABELS) as CommissionOrigin[])
        .filter((key) => positive(byOrigin[key]))
        .map((key) => ({
            key,
            label: ORIGIN_LABELS[key],
            amount: byOrigin[key],
        }));
}

const ORIGIN_ORDER: Record<CommissionOrigin, number> = {
    sales: 0,
    sessions: 1,
    goals: 2,
    revenue: 3,
};
const METRIC_ORDER: Record<GoalMetric, number> = {
    appointments: 0,
    approached: 1,
    sales: 2,
};

/** Extrato da pessoa: vendas, sessões e metas (por tipo, bônus junto do tipo), mais recentes primeiro. */
export function sortEntries(
    entries: CommissionEntryRow[],
): CommissionEntryRow[] {
    return [...entries].sort((a, b) => {
        const byOrigin = ORIGIN_ORDER[a.origin] - ORIGIN_ORDER[b.origin];
        if (byOrigin) return byOrigin;
        if (a.origin === "goals") {
            const byMetric =
                METRIC_ORDER[a.goal_metric ?? "sales"] -
                METRIC_ORDER[b.goal_metric ?? "sales"];
            if (byMetric) return byMetric;
            const kindOrder = (k: CommissionEntryRow["goal_kind"]) =>
                k === "tier" ? 0 : k === "bonus" ? 1 : 2;
            const byKind = kindOrder(a.goal_kind) - kindOrder(b.goal_kind);
            if (byKind) return byKind;
        }
        return (
            (b.occurred_at ?? "").localeCompare(a.occurred_at ?? "") ||
            b.id - a.id
        );
    });
}
