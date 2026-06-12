<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        <x-inertia::head />
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{-- VLibras — tradutor de Libras exigido para serviços públicos
             digitais (Lei 13.146/2015 / eMAG). Carregado fora do console
             interno (gestao*). Injetado no blade por ser script externo
             do governo e para persistir entre navegações do SPA. --}}
        @unless (request()->is('gestao*'))
            <div vw class="enabled" data-keep-contrast>
                <div vw-access-button class="active"></div>
                <div vw-plugin-wrapper>
                    <div class="vw-plugin-top-wrapper"></div>
                </div>
            </div>
            <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
            <script>
                window.addEventListener('load', function () {
                    if (window.VLibras) {
                        new window.VLibras.Widget('https://vlibras.gov.br/app');
                    }
                });
            </script>
        @endunless
    </body>
</html>
