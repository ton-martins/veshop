<!DOCTYPE html>
@php
    $component = $page['component'] ?? '';
    $shouldGateRender = $component === 'Public/Landing' || str_starts_with($component, 'Auth/');
    $publicStyleAssets = [
        '/landing/css/remixicon.css',
        '/landing/css/bootstrap.min.css',
        '/landing/css/style.min.css',
    ];
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/x-icon" href="/brand/favicon-veshop.ico?v=20260314">
        <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=20260314">
        <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-veshop-32.png?v=20260314">
        <link rel="icon" type="image/png" sizes="16x16" href="/brand/favicon-veshop-16.png?v=20260314">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @if ($shouldGateRender)
            @foreach ($publicStyleAssets as $styleHref)
                <link rel="preload" as="style" href="{{ $styleHref }}">
            @endforeach
            <style>
                html.veshop-preload body {
                    visibility: hidden;
                    opacity: 0;
                    background: #f5fdff;
                }

                html.veshop-ready body {
                    visibility: visible;
                    opacity: 1;
                    transition: opacity 180ms ease;
                }
            </style>
            <script>
                (function () {
                    var root = document.documentElement;
                    var requiredStyles = @json($publicStyleAssets);
                    var checkTimer = null;
                    var revealTimer = null;
                    var styleObserver = null;
                    var revealed = false;
                    var appMounted = false;

                    root.classList.add('veshop-preload');

                    var getStyleLink = function (href) {
                        return document.querySelector('link[rel="stylesheet"][href="' + href + '"]');
                    };

                    var isPublicComponent = function (componentName) {
                        if (!componentName || typeof componentName !== 'string') return false;
                        return componentName === 'Public/Landing' || componentName.indexOf('Auth/') === 0;
                    };

                    var isStyleReady = function (href) {
                        var link = getStyleLink(href);
                        return !!(link && link.sheet);
                    };

                    var areRequiredStylesReady = function () {
                        return requiredStyles.every(isStyleReady);
                    };

                    var reveal = function () {
                        if (revealed) return;
                        revealed = true;
                        root.classList.remove('veshop-preload');
                        root.classList.add('veshop-ready');

                        if (checkTimer) {
                            window.clearInterval(checkTimer);
                            checkTimer = null;
                        }
                        if (revealTimer) {
                            window.clearTimeout(revealTimer);
                            revealTimer = null;
                        }
                        if (styleObserver) {
                            styleObserver.disconnect();
                            styleObserver = null;
                        }
                    };

                    var ensureStylesheetsPresent = function () {
                        requiredStyles.forEach(function (href) {
                            if (getStyleLink(href)) return;
                            var link = document.createElement('link');
                            link.setAttribute('rel', 'stylesheet');
                            link.setAttribute('href', href);
                            link.setAttribute('data-veshop-public-style', '1');
                            document.head.appendChild(link);
                        });
                    };

                    var removeInjectedStylesheets = function () {
                        var injected = document.querySelectorAll('link[data-veshop-public-style="1"]');
                        injected.forEach(function (node) {
                            if (node && node.parentNode) {
                                node.parentNode.removeChild(node);
                            }
                        });
                    };

                    var tryRevealWhenReady = function () {
                        if (appMounted && areRequiredStylesReady()) {
                            reveal();
                        }
                    };

                    ensureStylesheetsPresent();
                    tryRevealWhenReady();

                    checkTimer = window.setInterval(tryRevealWhenReady, 40);
                    revealTimer = window.setTimeout(reveal, 8000);

                    if (typeof MutationObserver !== 'undefined') {
                        styleObserver = new MutationObserver(function () {
                            ensureStylesheetsPresent();
                            tryRevealWhenReady();
                        });

                        styleObserver.observe(document.head, {
                            childList: true,
                            subtree: true,
                            attributes: true,
                            attributeFilter: ['rel', 'href'],
                        });
                    }

                    window.addEventListener('load', tryRevealWhenReady, { once: true });
                    window.addEventListener('veshop:app-mounted', function () {
                        appMounted = true;
                        tryRevealWhenReady();
                    }, { once: true });

                    document.addEventListener('inertia:success', function (event) {
                        var nextComponent = event && event.detail && event.detail.page
                            ? event.detail.page.component
                            : '';

                        if (isPublicComponent(nextComponent)) {
                            ensureStylesheetsPresent();
                            tryRevealWhenReady();
                            return;
                        }

                        removeInjectedStylesheets();
                    });
                })();
            </script>
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
