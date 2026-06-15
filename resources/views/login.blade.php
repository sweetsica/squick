<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,.15);
            padding: 40px 32px;
            width: 100%;
            max-width: 360px;
        }
        .card h1 {
            margin: 0 0 4px;
            font-size: 22px;
            text-align: center;
            color: #1f2937;
        }
        .card p.sub {
            margin: 0 0 24px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }
        label {
            display: block;
            font-size: 13px;
            color: #374151;
            margin-bottom: 6px;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color .15s;
        }
        input[type="password"]:focus {
            border-color: #6366f1;
        }
        button {
            margin-top: 18px;
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 8px;
            background: #6366f1;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        button:hover { background: #4f46e5; }
        .error {
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Đăng nhập</h1>
        <p class="sub">Nhập mật khẩu để tiếp tục</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" autofocus required>
            <button type="submit">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
