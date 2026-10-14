{{-- Replaces the framework's error layout, whose inline styles the CSP blocks. --}}
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>@yield('code')</h1>
            <h2>@yield('message')</h2>
            <p><a href="/">Create a secret</a></p>
        </div>
    </div>
@endsection
