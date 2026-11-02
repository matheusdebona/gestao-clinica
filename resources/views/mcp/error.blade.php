@extends('mcp.layout')
@section('title', 'Não foi possível conectar')
@section('content')
    <h1>Não foi possível conectar</h1>
    <p>{{ $message }}</p>
    <p class="muted">Se precisar, fale com o administrador da clínica.</p>
@endsection
