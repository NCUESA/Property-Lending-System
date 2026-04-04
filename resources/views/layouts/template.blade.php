<!DOCTYPE html>
<html lang="zh-Hans-TW" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RSZ6NFNBZP"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', 'G-RSZ6NFNBZP');
    </script>

    @vite(['resources/css/app.css', 'resources/css/font.css', 'resources/css/constant.css', 'resources/css/color.css', 'resources/js/app.js'])

    <link rel="shortcut icon" href="{{ asset('/image/favicon.ico') }}">
    <title>器材借用網站 - 國立彰化師範大學學生會</title>
</head>

<body>
    <div class="d-flex">
        <div class="offcanvas-lg offcanvas-start bg-white border-end sidebar flex-shrink-0" id="sidebarMenu">
            <div class="offcanvas-body d-flex flex-column p-3 vh-100 sticky-top">
                <a href="/" class="d-flex align-items-center mb-3 text-dark text-decoration-none name-cn">
                    <span class="">器材借用系統</span>
                </a>
                <div class="small text-muted">
                    你好：{{ isset($hasAdminAccess) && $hasAdminAccess ? '高級管理員' : '一般管理員' }}， name
                    <br>
                    IP: {{ $clientIp }}
                </div>
                <hr>
                <div id="sidebar-app" data-path="/{{ ltrim(Request::path(), '/') }}"
                    data-is-admin="{{ isset($hasAdminAccess) && $hasAdminAccess ? 'true' : 'false' }}">>
                </div>

            </div>
        </div>

        <main class="flex-grow-1 p-4">
            @yield('content')
        </main>
    </div>

    @if (isset($js_name))
        <script type="module" src="{{ asset('js/' . $js_name . '.js') }}"></script>
    @endif
</body>

</html>
