<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use PDO;
use Throwable;
use Transliterator;

class CaseInsensitiveSearch
{
    /**
     * Latin letters with diacritics commonly found in pt-BR names (after lower()).
     * Used when the Postgres `unaccent` extension is not available.
     */
    private const TRANSLATE_FROM = 'áàâãäåāăąéèêëēėęíìîïīįóòôõöøōőúùûüūůűýÿçñšž';

    private const TRANSLATE_TO = 'aaaaaaaaaeeeeeeeiiiiiioooooooouuuuuuuyycnsz';

    /** @var array<int, true> */
    private static array $sqliteUnaccentPdos = [];

    private static ?bool $postgresUnaccentAvailable = null;

    /**
     * Case- and accent-insensitive contains (`unaccent` + `ILIKE` on Postgres).
     *
     * Columns are qualified so `whereHas` + clinic global scopes stay unambiguous.
     *
     * @param  Builder<*>  $query
     * @param  non-empty-list<string>  $columns
     */
    public static function whereContains(Builder $query, array $columns, string $term): void
    {
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            self::registerSqliteUnaccent($connection);
        }

        $postgresUnaccent = $driver === 'pgsql' && self::postgresHasUnaccent($connection);
        $pattern = '%'.$term.'%';

        $query->where(function (Builder $builder) use ($columns, $pattern, $driver, $postgresUnaccent): void {
            $grammar = $builder->getGrammar();

            foreach ($columns as $index => $column) {
                $wrapped = $grammar->wrap($builder->qualifyColumn($column));
                $sql = self::containsExpression($driver, $wrapped, $postgresUnaccent);

                if ($index === 0) {
                    $builder->whereRaw($sql, [$pattern]);

                    continue;
                }

                $builder->orWhereRaw($sql, [$pattern]);
            }
        });
    }

    /**
     * SQL predicate with a single `?` binding for the LIKE pattern.
     */
    public static function containsExpression(string $driver, string $wrappedColumn, bool $postgresUnaccent = true): string
    {
        if ($driver === 'pgsql') {
            if ($postgresUnaccent) {
                return 'unaccent(lower('.$wrappedColumn.'::text)) ilike unaccent(lower(?))';
            }

            $from = self::TRANSLATE_FROM;
            $to = self::TRANSLATE_TO;

            return 'translate(lower('.$wrappedColumn."::text), '{$from}', '{$to}') ilike translate(lower(?), '{$from}', '{$to}')";
        }

        return 'unaccent(lower('.$wrappedColumn.')) like unaccent(lower(?))';
    }

    public static function fold(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (extension_loaded('intl')) {
            $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');

            if ($transliterator instanceof Transliterator) {
                $folded = $transliterator->transliterate($value);

                if (is_string($folded)) {
                    return $folded;
                }
            }
        }

        return strtolower(Str::ascii($value));
    }

    public static function registerSqliteUnaccent(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();
        $id = spl_object_id($pdo);

        if (isset(self::$sqliteUnaccentPdos[$id])) {
            return;
        }

        $flags = defined('PDO::SQLITE_DETERMINISTIC') ? PDO::SQLITE_DETERMINISTIC : 0;

        $pdo->sqliteCreateFunction('unaccent', static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            return self::fold($value);
        }, 1, $flags);

        self::$sqliteUnaccentPdos[$id] = true;
    }

    public static function flushDriverState(): void
    {
        self::$sqliteUnaccentPdos = [];
        self::$postgresUnaccentAvailable = null;
    }

    private static function postgresHasUnaccent(Connection $connection): bool
    {
        if (self::$postgresUnaccentAvailable !== null) {
            return self::$postgresUnaccentAvailable;
        }

        try {
            $row = $connection->selectOne(
                'select exists (select 1 from pg_extension where extname = ?) as present',
                ['unaccent'],
            );

            self::$postgresUnaccentAvailable = (bool) ($row->present ?? false);
        } catch (Throwable) {
            self::$postgresUnaccentAvailable = false;
        }

        return self::$postgresUnaccentAvailable;
    }
}
