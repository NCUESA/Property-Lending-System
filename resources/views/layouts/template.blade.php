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

                <div class="">
                    @auth
                        <span
                            class="badge {{ match (auth()->user()->level) {
                                'admin' => 'bg-dark',
                                'normal' => 'bg-success',
                                default => '',
                            } }}">
                            {{ match (auth()->user()->level) {
                                'admin' => '高級管理員',
                                'normal' => '管理員',
                                default => '',
                            } }}
                        </span>
                        你好,{{ auth()->user()->student_id }}
                    @endauth
                </div>

                <hr>
                <div id="sidebar-app" data-path="/{{ ltrim(Request::path(), '/') }}"
                    data-user-level="{{ auth()->user() ? auth()->user()->level : 'guest' }}">
                </div>

                <div class="mt-auto flex-shrink-0 justify-content-between">
                    <div class="text-center mb-3">
                        <a href="https://github.com/NCUESA/Property-Lending-System" target="_blank" class="text-dark">
                            <i class="bi bi-github" style="font-size: 2rem;"></i>
                        </a>
                    </div>
                    <hr>
                    @auth
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-box-arrow-right"></i> 登出
                            </button>
                        </form>
                    @endauth

                    @guest
                        <a href="{{ route('login') }}" class="btn btn-primary w-100">
                            <i class="bi bi-person-fill"></i> NCUESA SSO 登入
                        </a>
                    @endguest

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
