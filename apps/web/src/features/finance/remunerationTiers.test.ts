import { describe, expect, it } from "vitest";
import {
    apiTierErrors,
    describeTier,
    emptyTierRow,
    formToPayload,
    summarizeTiers,
    tierPreview,
    tiersToForm,
    validateTiers,
    type TierFormRow,
} from "@/features/finance/remunerationTiers";
import { entrySubtitle, entryTitle, originBreakdown, sortEntries } from "@/features/finance/goalRemuneration";
import type { CommissionEntryRow } from "@/types/finance";

const nbsp = (s: string) => s.replace(/\u00a0/g, " ");

function row(threshold: string, fixed = "", percent = ""): TierFormRow {
    return { ...emptyTierRow(), threshold, fixed, percent };
}

describe("remunerationTiers", () => {
    it("converts the 70k/80k/100k example to the API payload", () => {
        const rows = [row("70000.00", "300.00"), row("80000.00", "500.00"), row("100000.00", "500.00", "1")];
        expect(validateTiers(rows, "revenue")).toEqual({});
        expect(formToPayload(rows, "revenue")).toEqual([
            { threshold: "70000.00", fixed_amount: "300.00", percent: null },
            { threshold: "80000.00", fixed_amount: "500.00", percent: null },
            { threshold: "100000.00", fixed_amount: "500.00", percent: "1" },
        ]);
        expect(nbsp(tierPreview(rows[2], "revenue"))).toBe(
            "Com faturamento de R$ 100.000,00, paga R$ 1.500,00.",
        );
    });

    it("validates thresholds, duplicates and empty amounts", () => {
        const errors = validateTiers(
            [row(""), row("1000.00", "10"), row("1000.00", "", "2"), row("2000.00", "", "150")],
            "revenue",
        );
        expect(errors["0.threshold"]).toBe("Informe o gatilho.");
        expect(errors["0.fixed"]).toBe("Informe um valor fixo, um percentual ou os dois.");
        expect(errors["2.threshold"]).toBe("Já existe uma faixa com este gatilho.");
        expect(errors["3.percent"]).toBe("Percentual entre 0 e 100.");
        expect(errors["1.threshold"]).toBeUndefined();

        expect(validateTiers([row("2,5", "10")], "appointments")["0.threshold"]).toBe(
            "Use um número inteiro a partir de 1.",
        );
        expect(validateTiers([row("0", "10")], "appointments")["0.threshold"]).toBeDefined();
        expect(formToPayload([row("3", "", "10,5")], "appointments")).toEqual([
            { threshold: 3, fixed_amount: null, percent: "10.5" },
        ]);
    });

    it("loads API tiers into the form and maps API errors back", () => {
        const form = tiersToForm([{ threshold: 3, fixed_amount: "0.00", percent: "10.5" }]);
        expect(form[0]).toMatchObject({ threshold: "3", fixed: "", percent: "10,5" });
        expect(
            apiTierErrors({ "tiers.1.fixed_amount": ["x"], "tiers.0.threshold": ["y"], other: ["z"] }),
        ).toEqual({ "1.fixed": "x", "0.threshold": "y" });
    });

    it("describes tiers for both kinds", () => {
        expect(nbsp(describeTier({ threshold: "100000.00", fixed_amount: "500.00", percent: "1" }, "revenue"))).toBe(
            "A partir de R$ 100.000,00: R$ 500,00 + 1% do faturamento",
        );
        expect(nbsp(describeTier({ threshold: 1, fixed_amount: "0.00", percent: "5" }, "appointments"))).toBe(
            "A partir de 1 agendamento: 5% das vendas dos pacientes",
        );
        expect(
            nbsp(
                summarizeTiers(
                    [
                        { threshold: "80000.00", fixed_amount: "500.00", percent: "0" },
                        { threshold: "70000.00", fixed_amount: "300.00", percent: "0" },
                    ],
                    "revenue",
                ),
            ),
        ).toBe("2 faixas, a partir de R$ 70.000,00");
        expect(summarizeTiers([], "revenue")).toBe("Sem faixas");
        expect(nbsp(tierPreview(row("10", "150", "10"), "appointments"))).toBe(
            "Com 10 agendamentos no mês, paga R$ 150,00 + 10% das vendas dos pacientes.",
        );
    });
});

function entry(partial: Partial<CommissionEntryRow>): CommissionEntryRow {
    return {
        id: 1, user_id: 1, user_name: "Rita", role: "revenue", origin: "revenue", goal_metric: null,
        goal_kind: "tier", tier_threshold: "100000.00", lead_id: null, sale_id: null, appointment_id: null,
        client_name: null, occurred_at: null, occurred_on: null, protocol_name: null, base_amount: "120000.00",
        rate_percent: "1.0000", amount: "1700.00", status: "pending", paid_at: null,
        ...partial,
    } as CommissionEntryRow;
}

describe("tier entries in Comissões", () => {
    it("labels revenue and appointment tier entries", () => {
        const revenue = entry({});
        expect(entryTitle(revenue)).toBe("Faturamento da clínica");
        expect(nbsp(entrySubtitle(revenue, "—"))).toBe(
            "Faixa: R$ 100.000,00 · 1% de R$ 120.000,00",
        );
        expect(nbsp(entrySubtitle({ ...revenue, rate_percent: null, tier_threshold: "70000.00", base_amount: "75000.00" }, "—"))).toBe(
            "Faixa: R$ 70.000,00 · faturamento R$ 75.000,00",
        );
        const appointments = entry({
            role: "goal", origin: "goals", goal_metric: "appointments", tier_threshold: "3.00",
            base_amount: "1000.00", rate_percent: "10.0000", amount: "250.00",
        });
        expect(entryTitle(appointments)).toBe("Faixa de agendamentos");
        expect(nbsp(entrySubtitle(appointments, "—"))).toBe("Faixa: 3 agendamentos · 10% de R$ 1.000,00");
        expect(nbsp(entrySubtitle({ ...appointments, rate_percent: null }, "—"))).toBe("Faixa: 3 agendamentos");
    });

    it("orders Faturamento last and lists it in the origin breakdown", () => {
        const sorted = sortEntries([
            entry({ id: 1 }),
            entry({ id: 2, role: "seller", origin: "sales", goal_kind: null }),
            entry({ id: 3, role: "goal", origin: "goals", goal_metric: "appointments", goal_kind: "occurrence" }),
            entry({ id: 4, role: "goal", origin: "goals", goal_metric: "appointments", goal_kind: "tier" }),
        ]);
        expect(sorted.map((e) => e.id)).toEqual([2, 4, 3, 1]);
        expect(
            originBreakdown({ sales: "0", sessions: "0", goals: "10.00", revenue: "1700.00" }).map((o) => o.label),
        ).toEqual(["Metas", "Faturamento"]);
    });
});
