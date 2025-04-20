<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '상재의 노래주머니')</title>
    <link rel="shortcut icon" href="/favicon.png" type="image/png">
    <link rel="stylesheet" href="{{ asset('CSS/music.css') }}" />
    <link rel="stylesheet" href="{{ asset('CSS/bootstrap.css') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
          integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <script src="{{ asset('CSS/JS/jquery-3.6.4.js') }}"></script>
    @stack('styles')
</head>
<body>
    {{-- 세션 디버깅을 위한 HTML 출력 코드 (임시 추가) --}}
    @if(Session::has('registered'))
        <p style="color: green;">세션에 'registered' 메시지가 있습니다: {{ Session::get('registered') }}</p>
    @endif

    @if(Session::has('loggedIn'))
        <p style="color: green;">세션에 'loggedIn' 메시지가 있습니다: {{ Session::get('loggedIn') }}</p>
    @endif
    {{-- 세션 디버깅 HTML 코드 끝 --}}


    <div class="container">
        @yield('content')
    </div>

    <script>
        // 세션에 저장된 'registered' (회원가입) 또는 'loggedIn' (로그인) 메시지를 확인하고 alert 창 띄우기
        // 이 스크립트 블록 안에는 JavaScript 코드만 있어야 합니다.
        @if(Session::has('registered'))
            alert("{{ Session::get('registered') }}");
        @elseif(Session::has('loggedIn'))
            alert("{{ Session::get('loggedIn') }}");
        @endif
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.3.1/js/bootstrap.bundle.min.js"></script>


    @stack('scripts')
</body>
</html>