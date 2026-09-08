<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\CaseInsensitiveSearch;
use Tests\TestCase;

class CaseInsensitiveSearchTest extends TestCase
{
    protected function tearDown(): void
    {
        CaseInsensitiveSearch::flushDriverState();
        parent::tearDown();
    }

    public function test_fold_strips_accents_and_lowercases(): void
    {
        $this->assertSame('jose', CaseInsensitiveSearch::fold('José'));
        $this->assertSame('toxina', CaseInsensitiveSearch::fold('Toxína'));
        $this->assertSame('acido hialuronico', CaseInsensitiveSearch::fold('Ácido hialurônico'));
    }

    public function test_postgres_expression_uses_unaccent_ilike(): void
    {
        $sql = CaseInsensitiveSearch::containsExpression('pgsql', '"name"', true);

        $this->assertSame('unaccent(lower("name"::text)) ilike unaccent(lower(?))', $sql);
    }

    public function test_postgres_fallback_translate_maps_are_aligned(): void
    {
        $sql = CaseInsensitiveSearch::containsExpression('pgsql', '"name"', false);

        $this->assertSame(1, preg_match(
            "/translate\\(lower\\(\"name\"::text\\), '(.+)', '(.+)'\\) ilike translate\\(lower\\(\\?\\), '(.+)', '(.+)'\\)/u",
            $sql,
            $matches,
        ));
        $this->assertSame(mb_strlen($matches[1]), mb_strlen($matches[2]));
        $this->assertSame($matches[1], $matches[3]);
        $this->assertSame($matches[2], $matches[4]);
    }

    public function test_sqlite_sql_uses_unaccent_and_qualifies_columns(): void
    {
        $query = Product::query();
        CaseInsensitiveSearch::whereContains($query, ['name', 'sku'], 'Botox');

        $sql = $query->toSql();

        $this->assertMatchesRegularExpression('/unaccent\(lower\("products"\."name"\)\) like unaccent\(lower\(\?\)\)/i', $sql);
        $this->assertMatchesRegularExpression('/unaccent\(lower\("products"\."sku"\)\) like unaccent\(lower\(\?\)\)/i', $sql);
    }
}
