<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" type="image/png" href="/favicon.png" sizes="any">

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Catamaran:wght@100..900&family=Knewave&display=swap" rel="stylesheet">

        @routes
        @viteReactRefresh
        {{-- app.tsx imports ../css/app.css, so the CSS rides along with this entry.
             Listing app.css separately breaks production builds (it isn't a Vite
             input, so it's absent from the manifest). --}}
        @vite(['resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="antialiased font-sans" style="font-family: 'Catamaran', sans-serif;">
        @inertia
    </body>
</html>
