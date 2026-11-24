{{-- Performance, PWA, and mobile-app viewport meta hints for NIS-REDAS --}}
<meta name="theme-color" content="#006633">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="NIS-REDAS">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="preload" href="{{ asset('nis-logo.png') }}" as="image" type="image/png">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/serviceworker.js').catch(function () {});
        });
    }
</script>
