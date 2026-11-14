<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Descadastro de e-mails</title>
    <style>
        :root { color-scheme: light dark; --bg: #f6f7f9; --card: #fff; --fg: #18181b; --muted: #52525b; --primary: #4f46e5; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f1115; --card: #181b21; --fg: #f4f4f5; --muted: #a1a1aa; --primary: #818cf8; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--fg); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; padding: 16px; box-sizing: border-box; }
        main { background: var(--card); border-radius: 12px; padding: 32px; max-width: 420px; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { color: var(--muted); margin: 0 0 20px; }
        button { background: var(--primary); color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font: inherit; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<main>
    @if($done)
        <h1>Pronto, você foi descadastrado.</h1>
        <p>Você não vai mais receber e-mails automáticos{{ $company ? ' de '.$company : '' }}.</p>
    @else
        <h1>Parar de receber e-mails?</h1>
        <p>Confirme para não receber mais e-mails automáticos{{ $company ? ' de '.$company : '' }}.</p>
        <form method="post" action="{{ $action }}">
            <button type="submit">Confirmar descadastro</button>
        </form>
    @endif
</main>
</body>
</html>
