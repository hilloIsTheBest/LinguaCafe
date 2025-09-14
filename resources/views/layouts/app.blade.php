<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="manifest" href="/manifest.json"> 
    <link rel="icon" type="image/png" href="/icon512rounded.png">
    <!-- iOS PWA support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LinguaCafe">
    <meta name="application-name" content="LinguaCafe">
    <meta name="format-detection" content="telephone=no">
    <link rel="apple-touch-icon" sizes="180x180" href="/icon512rounded.png">
    <script>
        (function(){
            try {
                var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch('/settings/global/get', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json','X-CSRF-TOKEN': token},
                    body: JSON.stringify({settingNames: ['siteTitle','siteIconUrl']})
                }).then(r=>r.ok?r.json():null).then(cfg=>{
                    if (!cfg) return;
                    if (cfg.siteTitle) document.title = cfg.siteTitle;
                    if (cfg.siteIconUrl) {
                        var l = document.querySelector('link[rel="icon"]');
                        if (l) l.href = cfg.siteIconUrl;
                    }
                }).catch(()=>{});
            } catch(e) {}
        })();
    </script>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>LinguaCafe</title>

    <script src="{{ mix('js/app.js') }}" defer></script>
    <link rel="dns-prefetch" href="//fonts.gstatic.com">

    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    <meta name="theme-color" content="#C5947D" />
    <script>
      // Android install prompt capture (optional hook for future UI button)
      window.deferredPWAInstallPrompt = null;
      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.deferredPWAInstallPrompt = e;
      });
    </script>

    <!-- These are dynamically set with javascript -->
    <style id="dynamic-default-font"></style>
    <style id="dynamic-selected-font"></style>
    
    @yield('header')
</head>
<body>
<div id="app">
    @yield('content')
</div>
</body></html>
