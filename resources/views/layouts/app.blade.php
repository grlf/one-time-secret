<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @if (request()->hasSession())
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif

    <title>{{ config('app.name') }}</title>

    <link rel="stylesheet" href="https://use.typekit.net/pxl0vxi.css">
    <link href="/images/favicon.ico" rel="icon" type="image/vnd.microsoft.icon">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="head-top"></div>
<header class="container">
    <div class="row">
        <div class="col-xs-12 col-sm-4 col-lg-6 logo">
            <div class="logo-image">
                <a href="https://greenleafmedia.com" title="Greenleaf Media"><span class="text-hide"></span></a>
                <h2 class="tagline visible-lg-block">
                    <a href="https://greenleafmedia.com/web-design">
                        Web Design, Web Development, Branding - Madison, Wisconsin<br />
                        A Full Service Website Design and Branding Agency
                    </a>
                </h2>
            </div>
        </div>
    </div>
</header>
<div class="container">
    @yield('content')
</div>
</body>
</html>
