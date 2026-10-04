@extends('layouts.app')

@section('content')
<div class="page">
    <h1>{{ $moduleName ?? ucwords(str_replace('-', ' ', $slug)) }}</h1>
    <div class="notice">
        <strong>Módulo preparado en la estructura del sistema.</strong>
        <p>La pantalla funcional de este módulo se implementará conforme a la especificación funcional definitiva y al modelo de datos.</p>
    </div>
</div>
@endsection
