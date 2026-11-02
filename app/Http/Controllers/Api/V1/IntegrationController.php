<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\McpAuditLog;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\McpConnections;
use Illuminate\Http\JsonResponse;

/**
 * Tela Integrações (Configurações > Integrações): catálogo de integrações.
 * A primeira é o Claude (MCP, só leitura): status, endereço do servidor,
 * conexões ativas (com Revogar) e as últimas consultas feitas.
 */
class IntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        $clinicId = (int) CurrentClinic::id();
        $connections = McpConnections::forClinic($clinicId);

        $recent = McpAuditLog::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (McpAuditLog $log) => [
                'id' => $log->id,
                'tool' => $log->tool,
                'user_name' => $log->user?->name,
                'status' => $log->status,
                'created_at' => $log->created_at?->toIso8601ZuluString(),
            ]);

        return response()->json([
            'data' => [
                [
                    'key' => 'claude_mcp',
                    'name' => 'Claude',
                    'read_only' => true,
                    'status' => $connections->isNotEmpty() ? 'connected' : 'not_connected',
                    'server_url' => config('mcp.server_url') ?: url('/mcp'),
                    'connections' => $connections,
                    'recent_calls' => $recent,
                ],
            ],
        ]);
    }

    public function revokeClaude(int $user, string $client): JsonResponse
    {
        // Só conexões de usuários desta clínica (o escopo da clínica vale para User).
        $owner = User::query()->findOrFail($user);
        $revoked = McpConnections::revoke($owner->id, $client);

        return response()->json(['message' => 'Acesso revogado.', 'revoked_tokens' => $revoked]);
    }
}
