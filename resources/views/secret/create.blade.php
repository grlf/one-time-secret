@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>One Time Secret</h1>
            @if(Session::has('success'))
                <p>
                    The link to view your secret is:
                </p>
                <div class="form-group">
                    <input type="text" value="{{ url(Session::get('success')) }}" class="form-control can-copy" readonly/>
                </div>
                <p>
                    Pass along this link.  The secret can be viewed one time and is then gone forever.
                </p>
            @else
                <h2>Paste a password, secret message or private link below.</h2>
                <form method="POST" action="/save">
                    @csrf
                    <div class="form-group">
                        <textarea name="secret" id="secret" rows="10" class="form-control" placeholder="Enter your secret here..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="expires">Lifetime</label>
                        <select name="expires" class="form-control" id="expires">
                            <option value="3600" selected>1 hour</option>
                            <option value="14400">4 hours</option>
                            <option value="86400">24 hours</option>
                            <option value="604800">1 week</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create link</button>
                </form>
            @endif
        </div>
    </div>
@endsection