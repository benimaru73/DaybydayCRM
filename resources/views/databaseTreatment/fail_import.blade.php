@extends('layouts.master')

@section('content')
    <div class="container">
        <h1>Echec de l'insertion : {{ $message }}</h1>
        <p>Une erreur est survenue pendant l'importation. Veuillez réessayer.</p>
    </div>
@endsection
