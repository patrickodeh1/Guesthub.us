<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Expired</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 px-4 text-slate-900">
    <main class="ui-enter w-full max-w-xl rounded-lg border border-slate-200 bg-white p-8 text-center shadow-xs">
        <span class="icon-chip mx-auto h-14 w-14"><x-icon name="alert-triangle" class="h-7 w-7" /></span>
        <p class="eyebrow mt-6">Page expired</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-950">This page went stale.</h1>
        <p class="mt-3 leading-7 text-slate-600">This usually happens if the page sat open for a while. Just reload and try again — you shouldn't need to sign back in.</p>
        <a href="javascript:location.reload()" class="btn-secondary mt-6">Reload page</a>
    </main>
</body>
</html>
