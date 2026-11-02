<?php

namespace App\Support;

use App\Models\McpAuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conexões do Claude (MCP) de uma clínica: tokens OAuth do Passport agrupados
 * por usuário + cliente OAuth. Revogar apaga o acesso na hora (tokens de acesso,
 * de renovação e códigos pendentes).
 */
class McpConnections
{
    /** @return Collection<int, array<string, mixed>> */
    public static function forClinic(int $clinicId): Collection
    {
        $now = CarbonImmutable::now();
        $userIds = User::query()->withoutGlobalScopes()->where('clinic_id', $clinicId)->pluck('id');
        if ($userIds->isEmpty()) {
            return collect();
        }

        $rows = DB::table('oauth_access_tokens as t')
            ->join('oauth_clients as c', 'c.id', '=', 't.client_id')
            ->leftJoin('oauth_refresh_tokens as r', function ($join) use ($now): void {
                $join->on('r.access_token_id', '=', 't.id')->where('r.revoked', false)->where('r.expires_at', '>', $now);
            })
            ->whereIn('t.user_id', $userIds)
            ->where('t.revoked', false)
            ->where(fn ($q) => $q->where('t.expires_at', '>', $now)->orWhereNotNull('r.id'))
            ->groupBy('t.user_id', 't.client_id', 'c.name')
            ->select('t.user_id', 't.client_id', 'c.name as client_name')
            ->selectRaw('MIN(t.created_at) as connected_at')
            ->selectRaw('MAX(COALESCE(r.expires_at, t.expires_at)) as expires_at')
            ->get();

        $users = User::query()->withoutGlobalScopes()->whereIn('id', $rows->pluck('user_id'))->pluck('name', 'id');
        $lastUse = McpAuditLog::query()->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->whereIn('oauth_client_id', $rows->pluck('client_id'))
            ->groupBy('user_id', 'oauth_client_id')
            ->selectRaw('user_id, oauth_client_id, MAX(created_at) as last_used_at, COUNT(*) as calls')
            ->get()
            ->keyBy(fn ($r) => $r->user_id.'|'.$r->oauth_client_id);

        return $rows->map(function ($r) use ($users, $lastUse) {
            $use = $lastUse->get($r->user_id.'|'.$r->client_id);

            return [
                'id' => $r->user_id.':'.$r->client_id,
                'user_id' => (int) $r->user_id,
                'user_name' => $users[$r->user_id] ?? '—',
                'client_id' => (string) $r->client_id,
                'client_name' => (string) $r->client_name,
                'connected_at' => self::iso($r->connected_at),
                'expires_at' => self::iso($r->expires_at),
                'last_used_at' => $use ? self::iso($use->last_used_at) : null,
                'calls' => $use ? (int) $use->calls : 0,
            ];
        })->sortByDesc(fn ($c) => $c['last_used_at'] ?? $c['connected_at'])->values();
    }

    /** Revoga a conexão (usuário + cliente). Devolve quantos tokens foram revogados. */
    public static function revoke(int $userId, string $clientId): int
    {
        return DB::transaction(function () use ($userId, $clientId): int {
            $tokenIds = DB::table('oauth_access_tokens')->where('user_id', $userId)->where('client_id', $clientId)->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            DB::table('oauth_auth_codes')->where('user_id', $userId)->where('client_id', $clientId)->update(['revoked' => true]);

            return DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->where('revoked', false)->update(['revoked' => true, 'updated_at' => now()]);
        });
    }

    /** Revoga todas as conexões do usuário (ex.: conta desativada ou sem permissão). */
    public static function revokeAllFor(int $userId): int
    {
        return DB::transaction(function () use ($userId): int {
            $tokenIds = DB::table('oauth_access_tokens')->where('user_id', $userId)->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            DB::table('oauth_auth_codes')->where('user_id', $userId)->update(['revoked' => true]);

            return DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->where('revoked', false)->update(['revoked' => true, 'updated_at' => now()]);
        });
    }

    private static function iso(mixed $value): ?string
    {
        return $value ? CarbonImmutable::parse($value, 'UTC')->toIso8601ZuluString() : null;
    }
}
