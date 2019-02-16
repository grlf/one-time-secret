@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>One Time Secret</h1>

            @if ($key)
                <form id="get-secret" style="display:none;">
                    @csrf
                    <input type="hidden" id="key" name="key" value="{{ $key }}" />
                </form>

                <div id="found" style="display:none;">
                    <p>The following is your secret.</p>
                    <div class="form-group">
                            <textarea rows="10" name="secret" id="secret" class="form-control can-copy" style="display: none;"readonly></textarea>
                    </div>
                    <p>
                        Copy this information and save it in a safe spot.  <strong>Once you refresh or navigate away from this page
                            it will be gone forever.</strong>
                    </p>
                </div>
                <div id="error-msg"></div>
            @endif
        </div>
    </div>

@endsection