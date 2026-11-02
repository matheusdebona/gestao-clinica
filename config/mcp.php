<?php

return [

    /*
    | Conector MCP do HOF Pay (Claude), só leitura. Ver routes/ai.php.
    */

    // Endereço público do servidor MCP mostrado na tela Integrações.
    'server_url' => env('MCP_SERVER_URL'),

    // Redirects OAuth aceitos no registro dinâmico (iguais, caractere por caractere).
    'allowed_redirect_uris' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'MCP_ALLOWED_REDIRECT_URIS',
        'https://claude.ai/api/mcp/auth_callback,https://claude.com/api/mcp/auth_callback',
    ))))),

    // Loopback (http://localhost:PORTA/...): só Claude Code e MCP Inspector precisam. Claude web,
    // Desktop e celular usam https://claude.ai/api/mcp/auth_callback. Desligado por padrão.
    'allow_loopback_redirects' => (bool) env('MCP_ALLOW_LOOPBACK_REDIRECTS', false),

    // Chamadas por minuto: por token e por usuário.
    'rate_limit_per_token' => (int) env('MCP_RATE_LIMIT_PER_TOKEN', 60),
    'rate_limit_per_user' => (int) env('MCP_RATE_LIMIT_PER_USER', 120),

    // Usados pelo pacote laravel/mcp (não usamos Mcp::oauthRoutes nem clientes MCP).
    'redirect_domains' => [],
    'custom_schemes' => [],
    'authorization_server' => null,
    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],
    'clients' => [],

];
