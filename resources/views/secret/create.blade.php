@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <h1>One Time Secret</h1>

            <noscript>
                <div class="alert alert-warning">
                    JavaScript is required. Your secret is encrypted in your browser before it is sent.
                </div>
            </noscript>

            <div id="error-msg" class="alert alert-danger" role="alert" hidden></div>

            <div id="share" hidden>
                <p>
                    The link to view your secret is:
                </p>
                <div class="row">
                    <div class="col-lg-10 mb-3">
                        <input type="text" id="my-link" class="form-control can-copy" aria-label="Link to your secret" autocomplete="off" readonly/>
                    </div>
                    <div class="col-lg-2 mb-3 d-grid">
                        <button type="button" class="btn btn-primary copy-btn" data-copy-selector="#my-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
                            </svg>
                            <span class="copy-label">Copy</span>
                        </button>
                    </div>
                </div>
                <p>
                    Pass along this link.  The secret can be viewed one time and is then gone forever.
                </p>
                <p>
                    <a href="/">Create another secret</a>
                </p>
            </div>

            {{-- No name attributes: if the script never runs, nothing can be submitted in plain text. --}}
            <form id="create-secret">
                <h2>Paste a password, secret message or private link below.</h2>
                <div class="mb-3">
                    <textarea id="secret" rows="10" class="form-control" placeholder="Enter your secret here..."
                              aria-label="Secret" maxlength="{{ $maxLength }}" required
                              autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                              data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false"></textarea>
                </div>
                <div class="mb-3">
                    <label for="expires" class="form-label">Lifetime</label>
                    <select class="form-select" id="expires">
                        <option value="3600" selected>1 hour</option>
                        <option value="14400">4 hours</option>
                        <option value="86400">24 hours</option>
                        <option value="604800">1 week</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" disabled>Create link</button>
            </form>
        </div>
    </div>
@endsection
