<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <title>Document</title>
    </head>
    <body>
        <div id="app"></div>

        <noscript>This app requires JavaScript, please enable your JavaScript.</noscript>
    </body>
</html>