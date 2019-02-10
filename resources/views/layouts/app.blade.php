<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="//fonts.googleapis.com/css?family=Open+Sans:400,300,400italic,600" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://use.typekit.net/pxl0vxi.css">
    <link href="/images/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon">

    <title>{{ env('APP_NAME') }}</title>
    <script src="{{ mix('/js/app.js') }}"></script>

    <!-- Fonts -->
    <link href="{{ mix('/css/app.css') }}" rel="stylesheet">

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
