<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · HOF Pay</title>
    <style>
        :root { color-scheme: light dark; --bg:#f5f6fb; --card:#ffffff; --text:#1d1d2b; --muted:#646478; --border:#dfe1ec; --primary:#5956a6; --primary-hover:#4f4c96; --on-primary:#fff; --danger:#b42318; --soft:#eef0f7; }
        @media (prefers-color-scheme: dark) { :root { --bg:#121220; --card:#1c1c2e; --text:#ececf4; --muted:#a3a3ba; --border:#2f2f46; --primary:#8b88e0; --primary-hover:#9c99ea; --on-primary:#141424; --danger:#ff8a80; --soft:#25253b; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; background:var(--bg); color:var(--text); font:16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width:100%; max-width:480px; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:28px; box-shadow:0 10px 30px rgba(20,20,50,.08); }
        .brand { display:flex; align-items:center; gap:10px; font-weight:600; margin-bottom:20px; }
        .brand svg { width:28px; height:28px; color:var(--primary); }
        h1 { font-size:22px; line-height:1.3; margin:0 0 6px; }
        p { margin:0 0 12px; }
        .muted { color:var(--muted); font-size:14px; }
        .box { background:var(--soft); border-radius:12px; padding:14px 16px; margin:16px 0; }
        .box dt { font-size:13px; color:var(--muted); }
        .box dd { margin:0 0 8px; font-weight:600; word-break:break-word; }
        ul { margin:8px 0 0; padding-left:20px; }
        li { margin:2px 0; }
        label { display:block; font-size:14px; font-weight:600; margin:14px 0 6px; }
        input[type=email], input[type=password] { width:100%; height:44px; padding:0 12px; font-size:16px; border:1px solid var(--border); border-radius:10px; background:var(--card); color:var(--text); }
        input:focus { outline:2px solid var(--primary); outline-offset:1px; }
        .error { color:var(--danger); font-size:14px; margin-top:12px; }
        .actions { display:flex; gap:12px; justify-content:space-between; margin-top:24px; }
        .actions form { margin:0; }
        .btn { display:inline-flex; align-items:center; justify-content:center; height:40px; padding:0 18px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; border:1px solid transparent; text-decoration:none; }
        .btn-primary { background:var(--primary); color:var(--on-primary); }
        .btn-primary:hover { background:var(--primary-hover); }
        .btn-ghost { background:transparent; color:var(--primary); border-color:var(--border); }
        .warn { border:1px solid #d97706; background:rgba(217,119,6,.12); border-radius:12px; padding:10px 12px; font-size:14px; margin:0 0 14px; }
        .badge { display:inline-block; font-size:12px; font-weight:600; padding:2px 8px; border-radius:999px; background:var(--soft); color:var(--primary); }
        @media (max-width: 560px) {
            .card { padding:22px 18px; }
            .actions { flex-direction:column-reverse; }
            .actions form, .actions .btn { width:100%; }
            .actions .btn { width:100%; }
        }
    </style>
</head>
<body>
<main class="card">
    <div class="brand">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.4-3 8.4-7 9.9C8 19.4 5 15.4 5 11V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
        HOF Pay
    </div>
    @yield('content')
</main>
</body>
</html>
