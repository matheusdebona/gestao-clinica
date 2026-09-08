<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class CaseInsensitiveSearch
{
    /**
     * Case-insensitive contains (`ILIKE` on Postgres, `LIKE` on SQLite/MySQL).
     *
     * @param  Builder<*>  $query
     * @param  non-empty-list<string>  $columns
     */
    public static function whereContains(Builder $query, array $columns, string $term): void
    {
        $pattern = '%'.$term.'%';

        $query->where(function (Builder $builder) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $builder->whereLike($column, $pattern);

                    continue;
                }

                $builder->orWhereLike($column, $pattern);
            }
        });
    }
}
