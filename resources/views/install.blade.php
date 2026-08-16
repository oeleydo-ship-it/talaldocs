<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Install · {{ $appName }}</title>
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
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }
        h1 { margin: 0 0 6px; font-size: 1.35rem; }
        .sub { margin: 0 0 20px; color: #9aa7b8; font-size: .925rem; line-height: 1.45; }
        .status {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #12171e;
            border: 1px solid #2a3340;
            font-size: .85rem;
            color: #9aa7b8;
        }
        .status strong { color: #e7ecf3; }
        .status.error { border-color: #7f1d1d; color: #fecaca; }
        label { display: block; margin: 0 0 6px; font-size: .85rem; color: #c4ceda; }
        input {
            width: 100%;
            margin-bottom: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #2a3340;
            background: #0f1419;
            color: #e7ecf3;
            font-size: 1rem;
        }
        input:focus { outline: 2px solid #3b82f6; border-color: transparent; }
        button {
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .error { color: #fca5a5; font-size: .8rem; margin: -8px 0 12px; }
        .errors { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #450a0a; color: #fecaca; font-size: .85rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Install {{ $appName }}</h1>
        <p class="sub">Create the first platform superadmin to finish setup.</p>

        <div class="status {{ $status['connected'] ? '' : 'error' }}">
            <div><strong>Database:</strong> {{ $driverLabel }} · {{ $status['connected'] ? 'Connected' : 'Not connected' }}</div>
            @if($status['connected'])
                <div>{{ $status['migrations_ready'] ? 'Schema ready' : 'Migrations will run on submit' }}</div>
            @endif
            @if(! $status['connected'] && $status['error'])
                <div style="margin-top:8px">{{ $status['error'] }}</div>
            @endif
        </div>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('install.store') }}">
            @csrf
            <label for="app_name">App name</label>
            <input id="app_name" name="app_name" value="{{ old('app_name', $appName) }}" placeholder="Docs" autocomplete="organization">

            <label for="name">Superadmin name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Full name">

            <label for="email">Superadmin email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="admin@example.com">

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Password">

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm password">

            <button type="submit" @disabled(! $status['connected'])>
                Create superadmin &amp; finish install
            </button>
        </form>
    </div>
</body>
</html>
