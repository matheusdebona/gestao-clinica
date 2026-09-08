<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function acceptBudgetForSale(int $saleId): int
    {
        $budgetId = $this->postJson("/api/v1/sales/{$saleId}/budgets")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/budgets/{$budgetId}/accept")->assertOk();

        return (int) $budgetId;
    }
}
