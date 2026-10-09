<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') · {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <style>
            *{box-sizing:border-box}
            body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fafafa;color:#09090b;font-family:"Instrument Sans",system-ui,sans-serif;font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased;padding:24px}
            .card{max-width:440px;width:100%;background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:32px;box-shadow:0 1px 3px rgba(9,9,11,.08)}
            .code{font-family:ui-monospace,monospace;font-size:12px;letter-spacing:.08em;color:#ef3b3a;font-weight:600}
            h1{margin:8px 0 6px;font-size:20px;font-weight:600;letter-spacing:-.01em}
            p{margin:0;color:#71717a}
            a.btn{display:inline-block;margin-top:20px;padding:8px 14px;border-radius:8px;background:#09090b;color:#fff;text-decoration:none;font-weight:500}
            a.btn:hover{background:#27272a}
            .brand{margin-top:24px;font-size:12px;color:#a1a1aa}
        </style>
    </head>
    <body>
        <main class="card" role="main">
            <div class="code">ERROR @yield('code')</div>
            <h1>@yield('message')</h1>
            <p>@yield('description')</p>
            <a class="btn" href="{{ url('/') }}">Volver a los catálogos</a>
            <div class="brand">{{ config('app.name') }} · Ogilvy × Cencosud</div>
        </main>
    </body>
</html>
