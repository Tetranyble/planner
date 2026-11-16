<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- Resolve appearance before CSS loads to prevent a light/dark flash. --}}
        <script>
            (() => {
                const cookie = document.cookie.split(';').map(x => x.trim()).find(x => x.startsWith('planner_theme='));
                const raw = cookie ? cookie.slice('planner_theme='.length) : 'system';
                let choice = 'system';
                try { const decoded = decodeURIComponent(raw); if (['light', 'dark', 'system'].includes(decoded)) choice = decoded; } catch (_) {}
                const systemDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
                const resolved = choice === 'system' ? (systemDark ? 'dark' : 'light') : choice;
                document.documentElement.classList.toggle('dark', resolved === 'dark');
                document.documentElement.dataset.theme = resolved;
                document.documentElement.dataset.themePreference = choice;
                document.documentElement.style.colorScheme = resolved;
            })();
        </script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Geist is bundled locally through @fontsource-variable/geist. --}}

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
