<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * Usuário visto pelo Passport (conector MCP do Claude).
 *
 * É a mesma tabela `users`, mas um modelo separado: o App\Models\User usa os
 * tokens do Sanctum (app web) e os dois traits HasApiTokens não convivem na
 * mesma classe. Depois de autenticar, o middleware EnsureMcpUser troca este
 * modelo pelo App\Models\User, com papéis, permissões e clínica.
 */
class McpOAuthUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $hidden = ['password', 'remember_token', 'temporary_password_hash'];
}
