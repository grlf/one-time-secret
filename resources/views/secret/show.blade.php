@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>One Time Secret</h1>

            @if ($secret)
                <p>The following is your secret.</p>
                <div class="form-group">
                    <textarea rows="10" name="secret" id="secret" class="form-control can-copy" readonly>{{ $secret }}</textarea>
                </div>
                <p>
                    Copy this information and save it in a safe spot.  Once you refresh or navigate away from this page
                    it will be gone forever.
                </p>
            @else
                <p>Your secret cannot be found.  Please ask the sender to try again.</p>
            @endif
        </div>
    </div>

@endsection