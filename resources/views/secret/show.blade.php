@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>One Time Secret</h1>

            <noscript>
                <div class="alert alert-warning">
                    JavaScript is required. The secret is decrypted in your browser.
                </div>
            </noscript>

            {{-- Revealing waits for a click so that link scanners which open
                 the page (some run JavaScript) can't burn the secret. --}}
            <div id="reveal">
                <p>
                    Someone has shared a secret with you. It can be viewed one time and is then gone forever.
                </p>
                <button type="button" id="reveal-btn" class="btn btn-primary mb-3" disabled>Reveal secret</button>
            </div>

            <div id="found" hidden>
                <p>The following is your secret.</p>
                <div class="mb-3">
                    <textarea rows="10" id="secret" class="form-control can-copy" aria-label="Secret" readonly
                              autocomplete="off" spellcheck="false"
                              data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false"></textarea>
                </div>
                <p>
                    Copy this information and save it in a safe spot.  <strong>Once you refresh or navigate away from this page
                        it will be gone forever.</strong>
                </p>
            </div>

            <div id="error-msg" class="alert alert-danger" role="alert" hidden></div>
        </div>
    </div>
@endsection
