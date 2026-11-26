@php
    $homeUrl   = url('/');
    $homeLabel = 'Kembali ke Halaman Utama';

    try {
        if (auth()->check()) {
            $u = auth()->user();
            if (method_exists($u, 'homeRouteName')) {
                $homeUrl   = route($u->homeRouteName());
                $homeLabel = method_exists($u, 'isPpic') && $u->isPpic()
                    ? 'Kembali ke Dashboard'
                    : 'Kembali ke Portal Scanner';
            }
        } else {
            $homeUrl   = route('login');
            $homeLabel = 'Masuk ke Sistem';
        }
    } catch (\Throwable $e) {
        $homeUrl = url('/');
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title') — DRC Stock Control</title>
    <style>
        :root {
            --navy: #0F1E33;
            --accent: #0B5CAD;
            --accent-hover: #094A8C;
            --bg: #F1F5F9;
            --card: #FFFFFF;
            --ink: #0F172A;
            --muted: #475569;
            --line: #CBD5E1;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: var(--navy);
            color: #FFFFFF;
            border-bottom: 3px solid var(--accent);
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .brand-main {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .brand-sub {
            font-size: 11px;
            color: #94A3B8;
        }
        .wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 30, 51, 0.08);
        }
        .card-body {
            padding: 26px 22px 22px;
            text-align: center;
        }
        .code-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            margin-bottom: 12px;
        }
        .tone-crit { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
        .tone-warn { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
        .tone-info { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
        h1 {
            margin: 0 0 8px;
            font-size: 19px;
            line-height: 1.3;
            color: var(--navy);
        }
        p {
            margin: 0;
            font-size: 13.5px;
            line-height: 1.55;
            color: var(--muted);
        }
        .tip {
            margin-top: 16px;
            padding: 10px 12px;
            border-radius: 6px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            font-size: 12px;
            color: #334155;
            text-align: left;
            line-height: 1.45;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 42px;
            padding: 9px 16px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--line);
            background: #FFFFFF;
            color: var(--ink);
            transition: background 0.15s ease;
        }
        .btn:hover { background: #F8FAFC; }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #FFFFFF;
        }
        .btn-primary:hover { background: var(--accent-hover); }
        .foot {
            padding: 10px 16px;
            background: #F8FAFC;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 11px;
            color: #64748B;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div>
            <div class="brand-main">DRC STOCK CONTROL</div>
            <div class="brand-sub">PT. Dian Raya Cipta</div>
        </div>
        <span style="font-size:11px; color:#CBD5E1;">Kode @yield('code')</span>
    </header>

    <main class="wrap">
        <section class="card" role="alert">
            <div class="card-body">
                <div class="code-badge @yield('tone', 'tone-info')">ERROR @yield('code')</div>
                <h1>@yield('title')</h1>
                <p>@yield('message')</p>

                @hasSection('tip')
                    <div class="tip">@yield('tip')</div>
                @endif

                <div class="actions">
                    @yield('actions')
                    <a href="{{ $homeUrl }}" class="btn @yield('home_btn_class', 'btn-primary')">{{ $homeLabel }}</a>
                </div>
            </div>
            <div class="foot">
                Jika kendala berulang, hubungi bagian PPIC.
            </div>
        </section>
    </main>
</body>
</html>