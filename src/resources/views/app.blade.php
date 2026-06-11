<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            (function () {
                try {
                    var storedTheme = window.localStorage.getItem('theme');
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var root = document.documentElement;

                    if (storedTheme === 'cyber') {
                        root.setAttribute('data-theme', 'cyber');
                        root.classList.remove('dark');
                    } else if (storedTheme === 'dark' || (!storedTheme && prefersDark)) {
                        root.setAttribute('data-theme', 'dark');
                        root.classList.add('dark');
                    } else {
                        root.setAttribute('data-theme', 'light');
                        root.classList.remove('dark');
                    }
                } catch (error) {
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="bg-white font-sans antialiased text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        @inertia
    </body>
</html>
