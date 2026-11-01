import { formatBRL } from "@/lib/formatters";
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

/** "R$ 80,00 por agendamento · bônus de R$ 300,00 ao bater a meta" ou "Sem remuneração". */
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
    return parts.length ? parts.join(" · ") : "Sem remuneração";
}

export function hasRemuneration(row: GoalRemunerationRow): boolean {
    return (
        (row.supports_per_occurrence && positive(row.per_occurrence_amount)) ||
        positive(row.target_bonus_amount)
    );
}

/** Primeira linha de um lançamento no extrato da pessoa. */
export function entryTitle(entry: CommissionEntryRow): string {
    if (entry.origin === "goals" && entry.goal_metric) {
        if (entry.goal_kind === "bonus") return BONUS_LABELS[entry.goal_metric];
        return `${OCCURRENCE_LABELS[entry.goal_metric]} · ${entry.client_name ?? "—"}`;
    }
    return `${entry.origin === "sessions" ? "Sessão" : "Venda"} · ${entry.client_name ?? "—"}`;
}

/** Segunda linha: protocolo (vendas/sessões) ou o tipo de meta. */
export function entrySubtitle(entry: CommissionEntryRow, date: string): string {
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

/** Origens com valor, na ordem Vendas, Sessões, Metas. */
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
            const byKind =
                (a.goal_kind === "bonus" ? 0 : 1) -
                (b.goal_kind === "bonus" ? 0 : 1);
            if (byKind) return byKind;
        }
        return (
            (b.occurred_at ?? "").localeCompare(a.occurred_at ?? "") ||
            b.id - a.id
        );
    });
}
