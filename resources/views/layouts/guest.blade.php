{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- ... head 내용 (CSS, JS 링크 등) ... --}}
</head>
<body class="hurts_body">
    <div class="font-sans text-gray-900 antialiased">
        @yield('content') {{-- 이 부분을 추가하거나 기존의 $slot을 대체 --}}
    </div>

    <footer class="hurts_footer">
        {{-- ... footer 내용 ... --}}
    </footer>

    {{-- ... body 하단의 JS 링크 등 ... --}}
</body>
</html>