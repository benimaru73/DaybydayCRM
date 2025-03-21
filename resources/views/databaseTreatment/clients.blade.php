@extends('layouts.master')

@section('content')
    <div class="container">
        <h2>Importer un fichier CSV</h2>
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        <form action="{{ url('/import/clients') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="file">Sélectionner un fichier CSV</label>
                <input type="file" name="csv_file" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary mt-2">Importer</button>
        </form>
    </div>
@endsection
