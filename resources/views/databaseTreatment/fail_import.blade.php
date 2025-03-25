@extends('layouts.master')

@section('content')
    <div class="container">
        <h1>Echec de l'insertion</h1>
        <p>Une erreur est survenue pendant l'importation. Veuillez réessayer.</p>

        @if(isset($message) && count($message) > 0)
            <div class="alert alert-danger">
                <ul>
                    @foreach($message as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
