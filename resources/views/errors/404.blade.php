<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 px-4 text-slate-900">
    <main class="ui-enter w-full max-w-xl rounded-lg border border-slate-200 bg-white p-8 text-center shadow-xs">
        <span class="icon-chip mx-auto h-14 w-14"><x-icon name="alert-triangle" class="h-7 w-7" /></span>
        <p class="eyebrow mt-6">Link unavailable</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-950">We could not find that page.</h1>
        <p class="mt-3 leading-7 text-slate-600">The guest link may be incomplete, expired, or typed incorrectly. Please contact guest services for help.</p>
    </main>
</body>
</html>
