@extends('mcp.layout')
@section('title', 'Permitir acesso')
@section('content')
    @php($redirectHost = parse_url((string) request()->query('redirect_uri', $client->redirect_uris[0] ?? ''), PHP_URL_HOST))
    @php($isClaude = in_array($redirectHost, ['claude.ai', 'claude.com'], true))
    {{-- O nome do cliente vem do registro dinâmico (qualquer um escolhe): o título usa o destino real. --}}
    <h1>Permitir que {{ $isClaude ? 'o Claude' : 'um aplicativo neste computador' }} leia os dados da clínica?</h1>
    @unless ($isClaude)
        <p class="warn" role="alert">Atenção: o retorno vai para <strong>{{ $redirectHost }}</strong>, não para o site do Claude. Só continue se foi você que iniciou esta conexão (ex.: Claude Code).</p>
    @endunless
    <p class="muted"><span class="badge">Só leitura</span> O Claude poderá consultar, mas nunca cadastrar, alterar ou apagar nada.</p>

    <dl class="box">
        <dt>Clínica</dt>
        <dd>{{ $clinicName }}</dd>
        <dt>Sua conta</dt>
        <dd>{{ $user->name }} ({{ $user->email }})</dd>
        @if ($redirectHost)
            <dt>Retorno para</dt>
            <dd>{{ $redirectHost }}</dd>
        @endif
    </dl>

    <p><strong>O que poderá ser lido</strong> (só o que sua conta já vê no app):</p>
    <ul>
        <li>Faturamento, vendas por produto e DRE</li>
        <li>Agenda de consultas e sessões</li>
        <li>Leads e pacientes (nome, faixa e histórico de compras)</li>
        <li>Metas e remuneração da equipe</li>
        <li>Estoque baixo, retornos e orçamentos</li>
    </ul>
    <p class="muted" style="margin-top:12px">Telefones, e-mails, CPF e anotações clínicas não são enviados. Você pode revogar o acesso a qualquer momento em Gestão &gt; Integrações.</p>

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn btn-ghost">Cancelar</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn btn-primary">Permitir acesso</button>
        </form>
    </div>
@endsection
