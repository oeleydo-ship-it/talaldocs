<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ucfirst($status) }} · {{ $hostname }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #0f1419;
            color: #e7ecf3;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: #171c24;
            border: 1px solid #2a3340;
            border-radius: 16px;
            padding: 28px;
        }
        h1 { margin: 0 0 8px; font-size: 1.25rem; }
        p { margin: 0; color: #9aa7b8; font-size: .95rem; line-height: 1.5; }
        code { color: #e7ecf3; }
    </style>
</head>
<body>
    <div class="card">
        <h1>
            @if ($status === 'failed')
                Custom domain is not verified
            @else
                Custom domain is pending
            @endif
        </h1>
        <p>
            <code>{{ $hostname }}</code>
            @if ($status === 'failed')
                is not serving documentation yet. Check the CNAME and ownership records in project settings, then verify again.
            @else
                is attached to a project but is not active yet. Documentation will appear here after DNS verification completes.
            @endif
            @if (filled($errorMessage ?? null))
                <br><br>{{ $errorMessage }}
            @endif
        </p>
    </div>
</body>
</html>
