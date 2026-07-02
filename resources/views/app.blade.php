@php($uiTheme = auth()->user()?->ui_theme === 'dark' ? 'dark' : 'light')
<!DOCTYPE html>
<html
    lang="en"
    data-theme="{{ $uiTheme }}"
    class="{{ $uiTheme === 'dark' ? 'dark' : '' }}"
    style="color-scheme: {{ $uiTheme }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
