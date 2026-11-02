@extends('mcp.layout')
@section('title', 'Entrar para conectar o Claude')
@section('content')
    <h1>Entrar para conectar o Claude</h1>
    <p class="muted">Use o mesmo e-mail e senha do HOF Pay. Depois você confirma o acesso, que é só leitura.</p>
    <form method="POST" action="{{ route('mcp.login.submit') }}" autocomplete="on">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        @if ($errors->any())
            <p class="error" role="alert">{{ $errors->first() }}</p>
        @endif
        <div class="actions">
            <span></span>
            <button type="submit" class="btn btn-primary">Entrar</button>
        </div>
    </form>
@endsection
