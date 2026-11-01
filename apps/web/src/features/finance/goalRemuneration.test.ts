import { describe, expect, it } from "vitest";
import {
    describeRemuneration,
    entrySubtitle,
    entryTitle,
    hasRemuneration,
    originBreakdown,
    perOccurrenceLabel,
    sortEntries,
} from "@/features/finance/goalRemuneration";
import type { CommissionEntryRow, GoalRemunerationRow } from "@/types/finance";

const nbsp = (s: string) => s.replace(/\u00a0/g, " ");

const appointments: GoalRemunerationRow = {
    metric: "appointments",
    label: "Novo agendamento",
    unit_label: "por agendamento",
    supports_per_occurrence: true,
    per_occurrence_amount: "80.00",
    target_bonus_amount: "0.00",
};

function entry(partial: Partial<CommissionEntryRow>): CommissionEntryRow {
    return {
        id: 1,
        user_id: 1,
        user_name: "Bruno",
        role: "goal",
        origin: "goals",
        goal_metric: "appointments",
        goal_kind: "occurrence",
        lead_id: 9,
        sale_id: null,
        appointment_id: null,
        client_name: "Lia Mota",
        occurred_at: "2026-10-10T12:00:00Z",
        protocol_name: null,
        base_amount: "80.00",
        rate_percent: null,
        amount: "80.00",
        status: "pending",
        paid_at: null,
        ...partial,
    };
}

describe("remuneração por meta", () => {
    it("descreve valor por ocorrência e bônus", () => {
        expect(nbsp(describeRemuneration(appointments))).toBe(
            "R$ 80,00 por agendamento",
        );
        expect(
            nbsp(
                describeRemuneration({
                    ...appointments,
                    target_bonus_amount: "300",
                }),
            ),
        ).toBe("R$ 80,00 por agendamento · bônus de R$ 300,00 ao bater a meta");
        expect(
            nbsp(
                describeRemuneration({
                    ...appointments,
                    metric: "sales",
                    supports_per_occurrence: false,
                    unit_label: null,
                    per_occurrence_amount: "0.00",
                    target_bonus_amount: "250",
                }),
            ),
        ).toBe("Bônus de R$ 250,00 ao bater a meta");
    });

    it('padrão zerado é "Sem remuneração"', () => {
        const zero = { ...appointments, per_occurrence_amount: "0.00" };
        expect(describeRemuneration(zero)).toBe("Sem remuneração");
        expect(hasRemuneration(zero)).toBe(false);
        expect(hasRemuneration(appointments)).toBe(true);
        expect(perOccurrenceLabel(appointments)).toBe("Valor por agendamento");
    });

    it("rotula lançamentos por origem", () => {
        expect(entryTitle(entry({}))).toBe("Novo agendamento · Lia Mota");
        expect(entryTitle(entry({ goal_kind: "bonus" }))).toBe(
            "Bônus: meta de agendamentos",
        );
        expect(
            entrySubtitle(
                entry({ goal_kind: "bonus", base_amount: "10.00" }),
                "",
            ),
        ).toBe("Meta do mês: 10");
        expect(
            nbsp(
                entrySubtitle(
                    entry({
                        goal_kind: "bonus",
                        goal_metric: "sales",
                        base_amount: "2000",
                    }),
                    "",
                ),
            ),
        ).toBe("Meta do mês: R$ 2.000,00");
        expect(
            entryTitle(
                entry({
                    origin: "sales",
                    role: "seller",
                    goal_metric: null,
                    goal_kind: null,
                }),
            ),
        ).toBe("Venda · Lia Mota");
        expect(
            entrySubtitle(
                entry({
                    origin: "sessions",
                    goal_metric: null,
                    goal_kind: null,
                    protocol_name: "Botox",
                }),
                "10/10/2026",
            ),
        ).toBe("Botox · 10/10/2026");
    });

    it("separa o total por origem e omite origens zeradas", () => {
        expect(
            originBreakdown({
                sales: "50.00",
                sessions: "0.00",
                goals: "160.00",
            }),
        ).toEqual([
            { key: "sales", label: "Vendas", amount: "50.00" },
            { key: "goals", label: "Metas", amount: "160.00" },
        ]);
        expect(originBreakdown(undefined)).toEqual([]);
    });
    it("ordena o extrato: vendas, sessões, metas por tipo com o bônus primeiro", () => {
        const rows = [
            entry({
                id: 1,
                goal_metric: "approached",
                occurred_at: "2026-10-02",
            }),
            entry({
                id: 2,
                goal_metric: "appointments",
                occurred_at: "2026-10-01",
            }),
            entry({
                id: 3,
                goal_metric: "appointments",
                goal_kind: "bonus",
                occurred_at: null,
            }),
            entry({
                id: 4,
                origin: "sales",
                role: "seller",
                goal_metric: null,
                goal_kind: null,
            }),
            entry({
                id: 5,
                goal_metric: "appointments",
                occurred_at: "2026-10-05",
            }),
            entry({
                id: 6,
                origin: "sessions",
                role: "professional",
                goal_metric: null,
                goal_kind: null,
            }),
        ];
        expect(sortEntries(rows).map((e) => e.id)).toEqual([4, 6, 3, 5, 2, 1]);
    });
});
