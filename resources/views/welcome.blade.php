<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Form — Training, tracked.</title>
    <script>try{document.documentElement.dataset.theme=localStorage.getItem('fitness-theme')||(matchMedia('(prefers-color-scheme: light)').matches?'light':'dark')}catch{document.documentElement.dataset.theme='dark'}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body><div id="app" aria-live="polite"><div class="loading">Loading your training space…</div></div></body>
</html>
