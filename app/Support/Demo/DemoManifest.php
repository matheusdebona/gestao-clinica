<?php

namespace App\Support\Demo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Lista de tudo que o seed criou (tabela => ids), gravada em storage/app/demo-seed/clinic-{id}.json.
 * O purge só apaga o que está aqui (e o que o próprio app derivou desses registros).
 */
final class DemoManifest
{
    /** Pasta dos manifestos (os testes apontam para uma pasta temporária). */
    public static ?string $directory = null;

    private static ?int $listeningOn = null;

    private static ?self $recording = null;

    /** @var array<string, list<int>> */
    public array $created = [];

    public static function path(int $clinicId): string
    {
        return (self::$directory ?? storage_path('app/demo-seed'))."/clinic-{$clinicId}.json";
    }

    public static function exists(int $clinicId): bool
    {
        return is_file(self::path($clinicId));
    }

    /** Começa a anotar todo model criado (evento eloquent.created). */
    public function record(): void
    {
        $dispatcher = spl_object_id(app('events'));
        if (self::$listeningOn !== $dispatcher) {
            Event::listen('eloquent.created: *', function (string $event, array $payload): void {
                $model = $payload[0] ?? null;
                if (self::$recording !== null && $model instanceof Model) {
                    self::$recording->created[$model->getTable()][] = (int) $model->getKey();
                }
            });
            self::$listeningOn = $dispatcher;
        }
        self::$recording = $this;
    }

    public function stop(): void
    {
        self::$recording = null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function save(int $clinicId, array $meta): string
    {
        $path = self::path($clinicId);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0750, true);
        }
        $created = array_map(fn (array $ids) => array_values(array_unique($ids)), $this->created);
        ksort($created);
        $json = json_encode(['meta' => $meta + ['clinic_id' => $clinicId], 'created' => $created], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new RuntimeException("Não consegui gravar o manifesto em {$path}");
        }
        @chmod($path, 0640);

        return $path;
    }

    /**
     * @return array{meta: array<string, mixed>, created: array<string, list<int>>}
     */
    public static function load(int $clinicId): array
    {
        $path = self::path($clinicId);
        if (! is_file($path)) {
            throw new RuntimeException("Manifesto não encontrado: {$path}");
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || (int) ($data['meta']['clinic_id'] ?? 0) !== $clinicId) {
            throw new RuntimeException('Manifesto inválido ou de outra clínica.');
        }

        return $data;
    }
}
