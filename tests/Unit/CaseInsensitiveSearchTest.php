<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\CaseInsensitiveSearch;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Tests\TestCase;

class CaseInsensitiveSearchTest extends TestCase
{
    public function test_postgres_grammar_compiles_contains_as_ilike(): void
    {
        $query = Product::query();
        CaseInsensitiveSearch::whereContains($query, ['name', 'sku'], 'Botox');

        $base = $query->getQuery();
        $sql = (new PostgresGrammar($base->getConnection()))->compileSelect($base);

        $this->assertMatchesRegularExpression('/"name"::text ilike \?/i', $sql);
        $this->assertMatchesRegularExpression('/"sku"::text ilike \?/i', $sql);
    }
}
