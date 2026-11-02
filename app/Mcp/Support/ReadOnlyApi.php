<?php

namespace App\Mcp\Support;

use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Leitura dos mesmos endpoints do app (GET /api/v1/...), como o próprio usuário.
 *
 * As ferramentas MCP não recalculam nada: chamam as rotas que o app usa, com os
 * mesmos middlewares de permissão (permission:...), escopo da clínica e validação.
 * Assim os números batem com as telas e o que o usuário não vê no app o Claude
 * também não vê.
 *
 * Garantias de só leitura:
 *  - só GET, e só para caminhos da lista ALLOWED;
 *  - tudo roda dentro de uma transação que é sempre desfeita (rollback) e, no
 *    Postgres, marcada como READ ONLY: uma escrita falharia na hora e nada
 *    fica gravado no banco.
 */
class ReadOnlyApi
{
    /** Caminhos liberados (regex sobre o caminho depois de /api/v1/). */
    public const ALLOWED = [
        '#^metrics/(commercial|product-sales|inventory|leads)$#',
        '#^finance/(dre|goal-remunerations|revenue-remunerations|commissions)$#',
        '#^appointments$#',
        '#^leads$#',
        '#^lead-statuses$#',
        '#^patient-tiers$#',
        '#^clients(/\d+)?$#',
        '#^sales$#',
        '#^budgets$#',
        '#^patient-returns$#',
        '#^goals$#',
        '#^clinics/current$#',
    ];

    public function __construct(private readonly Router $router) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(User $user, string $path, array $query = []): array
    {
        $path = trim($path, '/');
        if (! self::allows($path)) {
            throw new McpToolException('Consulta não permitida.');
        }

        $app = app();
        $auth = Auth::getFacadeRoot();
        $originalRequest = $app->make('request');
        $originalGuard = $auth->getDefaultDriver();

        $sub = Request::create('/api/v1/'.$path, 'GET', array_filter($query, fn ($v) => $v !== null && $v !== ''));
        $sub->headers->set('Accept', 'application/json');
        $sub->setUserResolver(fn () => $user);
        $auth->guard('sanctum')->setUser($user);

        DB::beginTransaction();
        try {
            // No Postgres a transação também é marcada como só leitura: qualquer escrita falha.
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SET LOCAL transaction_read_only = on');
            }

            $app->instance('request', $sub);
            $response = $this->router->dispatch($sub);
        } catch (Throwable $e) {
            report($e);
            throw new McpToolException('Não foi possível consultar agora. Tente de novo em instantes.');
        } finally {
            DB::rollBack();
            $app->instance('request', $originalRequest);
            $auth->shouldUse($originalGuard);
            $auth->guard('sanctum')->forgetUser();
            CurrentClinic::setId($user->clinic_id);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getContent(), true);

        if ($status === 403) {
            throw new McpToolException('Sua conta não tem permissão para ver isto no HOF Pay.');
        }
        if ($status === 404) {
            throw new McpToolException('Não encontrado nesta clínica.');
        }
        if ($status === 422) {
            $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
            $first = collect($errors)->flatten()->first();

            throw new McpToolException('Parâmetros inválidos: '.($first ?? ($body['message'] ?? 'confira os filtros.')));
        }
        if ($status >= 400 || ! is_array($body)) {
            throw new McpToolException('Não foi possível consultar agora. Tente de novo em instantes.');
        }

        return $body;
    }

    public static function allows(string $path): bool
    {
        foreach (self::ALLOWED as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
