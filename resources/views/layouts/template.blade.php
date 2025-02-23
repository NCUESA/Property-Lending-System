<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link href="{{ asset('css/index.css') }}" rel="stylesheet">
    <link rel="shortcut icon" href="{{ asset('/favicon.png') }}">
    <link rel="bookmark" href="{{ asset('/favicon.png') }}">
    <title>彰師學生會器材借用網站</title>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand name-cn" href="./">學生會器材借用系統</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="./">借用表單</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="./status">器材借用狀態</a>
                    </li>
                    @if ($hasAccess)
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="./full_status">器材借用狀態(管理員)</a>
                        </li>
                    @endif
                    @if ($hasAdminAccess)
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="./maintain">器材清單維護(管理員)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="./responsible">人員清單管理(管理員)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="./ip">IP通過權限設定(管理員)</a>
                        </li>
                    @endif


                    <!--
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Dropdown
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">Action</a></li>
                            <li><a class="dropdown-item" href="#">Another action</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#">Something else here</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled">Disabled</a>
                    </li>
                -->
                </ul>

            </div>
            <!-- 新增 IP 和權限顯示 -->
            <div class="d-flex align-items-center">
                <span class="badge bg-primary me-2">
                    IP：{{ $clientIp }}
                </span>
                <span class="badge {{ $hasAdminAccess ? 'bg-black' : ($hasAccess ? 'bg-success' : 'bg-secondary') }}">
                    權限：{{ $hasAdminAccess ? '高級管理員' : ($hasAccess ? '一般管理員' : '訪客') }}
                </span>
            </div>
    </nav>

    <div class="container">
        @yield('content')
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"
        integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
        integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous">
    </script>
    <script src="{{ asset('js/' . $js_name . '.js') }}"></script>
</body>

</html>
