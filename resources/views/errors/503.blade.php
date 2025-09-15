<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Wartungsarbeiten</title>
    <meta name="robots" content="noindex, nofollow" />
    <style>
        :root{
            --bg-1:#0b1220; --bg-2:#0e1628;
            --fg:#e9eef7; --muted:#b8c4d9;
            --angle: 0deg;
            --border-thickness: 6px;
            --border-offset: 3px;
        }

        @property --angle {
            syntax: "<angle>";
            initial-value: 0deg;
            inherits: false;
        }

        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color:var(--fg);
            background:
                radial-gradient(1000px 600px at 15% 10%, #0f1a3a 0%, transparent 60%),
                radial-gradient(900px 700px at 85% 90%, #0a1f2e 0%, transparent 60%),
                linear-gradient(180deg, var(--bg-1), var(--bg-2));
            display:grid;
            place-items:center;
            padding:28px;
        }

        .shell{
            position:relative;
            width:100%;
            max-width:920px;
            border-radius:22px;
            overflow:hidden;
            background:linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.02));
            box-shadow:0 22px 60px rgba(0,0,0,.50);
            isolation:isolate;
        }

        .shell::before{
            content:"";
            position:absolute;

            top:calc(var(--border-offset) * -1);
            left:calc(var(--border-offset) * -1);
            right:calc(var(--border-offset) * -1);
            bottom:calc(var(--border-offset) * -1);
            border-radius:calc(22px + var(--border-offset));

            padding:var(--border-thickness);
            background: conic-gradient(
                from var(--angle),
                #8b5cf6,
                #60a5fa,
                #22d3ee,
                #22c55e,
                #eab308,
                #f97316,
                #ec4899,
                #8b5cf6
            );
            background-size: 100% 100%;

            -webkit-mask:
                linear-gradient(#000 0 0) content-box,
                linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;

            pointer-events:none;
            z-index:3;

            animation: spinBorder 10s linear infinite;
            filter: drop-shadow(0 0 8px rgba(139,92,246,.25));
        }

        @keyframes spinBorder{
            to { --angle: 360deg; }
        }

        .outline{
            position:absolute; inset:0;
            z-index:3; pointer-events:none;
        }
        svg{ display:block; width:100%; height:100%; }



        .grid{
            display:grid;
            grid-template-columns: 1.1fr .9fr;
            position:relative;
            z-index:2;
        }
        @media (max-width: 820px){
            .grid{grid-template-columns:1fr}
            .art{min-height:200px}
        }

        .copy{
            padding:40px 42px 46px;
            background:linear-gradient(180deg, rgba(9,14,28,.35), rgba(9,14,28,0));
        }
        .badge{
            display:inline-flex; align-items:center; gap:.5ch;
            font-size:.82rem; letter-spacing:.3px; color:var(--muted);
            border:1px solid rgba(255,255,255,.14);
            background:rgba(255,255,255,.05);
            padding:6px 10px; border-radius:999px;
        }
        .title{
            font-size: clamp(26px, 4vw, 40px);
            line-height:1.12; margin:14px 0 10px; letter-spacing:.2px;
        }
        .subtitle{
            margin:0; color:var(--muted);
            font-size: clamp(14px, 2vw, 16px);
        }
        .rule{
            margin:22px 0 0; height:1px; width:100%; border:0;
            background:linear-gradient(90deg, transparent, rgba(255,255,255,.12), transparent);
        }

        .art{
            position:relative;
            background:
                radial-gradient(600px 320px at 70% 30%, rgba(96,165,250,.30), transparent 55%),
                radial-gradient(600px 320px at 30% 70%, rgba(34,197,94,.28), transparent 55%),
                linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
            border-left:1px solid rgba(255,255,255,.08);
        }
        .ornament{
            position:absolute; inset:0; pointer-events:none; opacity:.5;
            background:
                linear-gradient(0deg, transparent 48%, rgba(255,255,255,.06) 49%, rgba(255,255,255,.06) 51%, transparent 52%) center/100% 40px,
                linear-gradient(90deg, transparent 48%, rgba(255,255,255,.05) 49%, rgba(255,255,255,.05) 51%, transparent 52%) center/40px 100%;
            mask-image: radial-gradient(600px 400px at 50% 50%, #000 0%, transparent 70%);
        }
    </style>
</head>
<body>
<section class="shell" role="status" aria-live="polite">
    <div class="grid">
        <div class="copy">
            <span class="badge">Status • Wartungsarbeiten</span>
            <h1 class="title">Kurzzeitig nicht verfügbar</h1>
            <p class="subtitle">Bald wieder online.</p>
            <hr class="rule" />
        </div>
        <div class="art" aria-hidden="true">
            <div class="ornament"></div>
        </div>
    </div>
</section>
</body>
</html>
