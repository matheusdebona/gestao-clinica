import { isValidPercent, percentToInput, toPercent } from "@/features/finance/api";
import { emptyToMoney, formatBRL, formatPercent } from "@/lib/formatters";
import type {
    RemunerationTier,
    RemunerationTierPayload,
} from "@/types/finance";

/**
 * Faixas de remuneração (um modelo para dois usos):
 *  - appointments: gatilho = nº de novos agendamentos no mês; % sobre as vendas dos pacientes;
 *  - revenue: gatilho = faturamento total da clínica no mês; % sobre o faturamento total.
 * Só a maior faixa atingida vale (não acumula).
 */
export type TierKind = "appointments" | "revenue";

export const MAX_TIERS = 10;

export interface TierFormRow {
    key: number;
    threshold: string;
    fixed: string;
    percent: string;
}

export type TierErrors = Record<string, string>;

const PERCENT_BASE: Record<TierKind, string> = {
    appointments: "das vendas dos pacientes",
    revenue: "do faturamento",
};

let nextKey = 1;

export function emptyTierRow(): TierFormRow {
    return { key: nextKey++, threshold: "", fixed: "", percent: "" };
}

export function tiersToForm(tiers: RemunerationTier[] | undefined): TierFormRow[] {
    return (tiers ?? []).map((t) => ({
        key: nextKey++,
        threshold: String(t.threshold),
        fixed: Number(t.fixed_amount) > 0 ? String(t.fixed_amount) : "",
        percent: Number(t.percent) > 0 ? percentToInput(t.percent) : "",
    }));
}

function thresholdValue(row: TierFormRow, kind: TierKind): number | null {
    const raw = row.threshold.trim();
    if (!raw) return null;
    if (kind === "appointments") {
        return /^\d+$/.test(raw) ? Number(raw) : NaN;
    }
    const money = emptyToMoney(raw);
    return money === null ? null : Number(money);
}

/** Erros por campo, chave `${índice}.threshold|fixed|percent`. Vazio = válido. */
export function validateTiers(rows: TierFormRow[], kind: TierKind): TierErrors {
    const errors: TierErrors = {};
    const seen = new Set<number>();
    rows.forEach((row, i) => {
        const threshold = thresholdValue(row, kind);
        if (threshold === null) {
            errors[`${i}.threshold`] = "Informe o gatilho.";
        } else if (!Number.isFinite(threshold) || threshold <= 0) {
            errors[`${i}.threshold`] =
                kind === "appointments"
                    ? "Use um número inteiro a partir de 1."
                    : "Informe um valor maior que zero.";
        } else if (seen.has(threshold)) {
            errors[`${i}.threshold`] = "Já existe uma faixa com este gatilho.";
        } else {
            seen.add(threshold);
        }
        if (!isValidPercent(row.percent)) {
            errors[`${i}.percent`] = "Percentual entre 0 e 100.";
        }
        const fixed = Number(emptyToMoney(row.fixed) ?? 0);
        const percent = Number(toPercent(row.percent) ?? 0);
        if (!(fixed > 0) && !(percent > 0) && !errors[`${i}.percent`]) {
            errors[`${i}.fixed`] = "Informe um valor fixo, um percentual ou os dois.";
        }
    });
    return errors;
}

export function formToPayload(rows: TierFormRow[], kind: TierKind): RemunerationTierPayload[] {
    return rows.map((row) => {
        const threshold = thresholdValue(row, kind);
        return {
            threshold:
                kind === "appointments"
                    ? Number(threshold)
                    : (emptyToMoney(row.threshold) ?? "0"),
            fixed_amount: emptyToMoney(row.fixed),
            percent: toPercent(row.percent),
        };
    });
}

/** Erros da API (`tiers.0.fixed_amount`) no formato do formulário (`0.fixed`). */
export function apiTierErrors(errors: Record<string, string[]> | undefined): TierErrors {
    const out: TierErrors = {};
    for (const [key, messages] of Object.entries(errors ?? {})) {
        const m = /^tiers\.(\d+)\.(threshold|fixed_amount|percent)$/.exec(key);
        if (!m || !messages?.length) continue;
        out[`${m[1]}.${m[2] === "fixed_amount" ? "fixed" : m[2]}`] = messages[0];
    }
    return out;
}

export function thresholdLabel(threshold: string | number, kind: TierKind): string {
    if (kind === "appointments") {
        const n = Number(threshold);
        return `${n} ${n === 1 ? "agendamento" : "agendamentos"}`;
    }
    return formatBRL(threshold);
}

/** "R$ 500,00 + 1% do faturamento" (sem o gatilho). */
export function tierAmountLabel(
    tier: Pick<RemunerationTier, "fixed_amount" | "percent">,
    kind: TierKind,
): string {
    const parts: string[] = [];
    if (Number(tier.fixed_amount) > 0) parts.push(formatBRL(tier.fixed_amount));
    if (Number(tier.percent) > 0) {
        parts.push(`${formatPercent(tier.percent)} ${PERCENT_BASE[kind]}`);
    }
    return parts.join(" + ") || "—";
}

/** "A partir de R$ 70.000,00: R$ 300,00". */
export function describeTier(tier: RemunerationTier, kind: TierKind): string {
    return `A partir de ${thresholdLabel(tier.threshold, kind)}: ${tierAmountLabel(tier, kind)}`;
}

/** Resumo curto de uma lista de faixas, ex.: "3 faixas, a partir de R$ 70.000,00". */
export function summarizeTiers(tiers: RemunerationTier[] | undefined, kind: TierKind): string {
    const list = [...(tiers ?? [])].sort((a, b) => Number(a.threshold) - Number(b.threshold));
    if (list.length === 0) return "Sem faixas";
    if (list.length === 1) return describeTier(list[0], kind);
    return `${list.length} faixas, a partir de ${thresholdLabel(list[0].threshold, kind)}`;
}

/** Exemplo ao vivo de uma faixa válida (faturamento: o próprio gatilho como base). */
export function tierPreview(row: TierFormRow, kind: TierKind): string {
    const threshold = thresholdValue(row, kind);
    if (threshold === null || !Number.isFinite(threshold) || threshold <= 0) return "";
    const fixed = Number(emptyToMoney(row.fixed) ?? 0);
    const percent = Number(toPercent(row.percent) ?? 0);
    if (!(fixed > 0) && !(percent > 0)) return "";
    if (kind === "revenue") {
        const total = Math.round((fixed + (threshold * percent) / 100) * 100) / 100;
        return `Com faturamento de ${formatBRL(threshold)}, paga ${formatBRL(total)}.`;
    }
    const parts = [fixed > 0 ? formatBRL(fixed) : "", percent > 0 ? `${formatPercent(percent)} das vendas dos pacientes` : ""].filter(Boolean);
    return `Com ${thresholdLabel(threshold, kind)} no mês, paga ${parts.join(" + ")}.`;
}
