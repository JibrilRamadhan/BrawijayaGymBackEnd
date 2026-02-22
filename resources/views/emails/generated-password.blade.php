<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #fff;
            padding: 40px 20px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            background: #1a1a1a;
            border: 1px solid #333;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #ea580c;
            font-size: 24px;
            margin: 0;
        }

        .header p {
            color: #999;
            margin-top: 8px;
        }

        .password-box {
            background: #000;
            border: 2px solid #ea580c;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }

        .password-box .label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .password-box .password {
            font-size: 28px;
            font-weight: bold;
            color: #ea580c;
            letter-spacing: 4px;
            font-family: monospace;
        }

        .info {
            color: #999;
            font-size: 14px;
            line-height: 1.6;
        }

        .info strong {
            color: #fff;
        }

        .warning {
            background: #ea580c20;
            border: 1px solid #ea580c40;
            padding: 12px;
            margin-top: 20px;
            color: #fb923c;
            font-size: 13px;
        }

        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>BRAWIJAYA GYM</h1>
            <p>Selamat Bergabung, {{ $userName }}!</p>
        </div>

        <div class="info">
            <p>Akun member Anda telah berhasil dibuat. Berikut adalah password yang telah kami generate untuk login ke
                dashboard Anda:</p>
        </div>

        <div class="password-box">
            <div class="label">Password Anda</div>
            <div class="password">{{ $generatedPassword }}</div>
        </div>

        <div class="info">
            <p>Gunakan <strong>email Anda</strong> dan password di atas untuk login di website kami.</p>
        </div>

        <div class="warning">
            ⚠️ Simpan password ini dengan baik. Kami sarankan untuk segera mengubah password setelah login pertama.
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Brawijaya Gym. All rights reserved.</p>
        </div>
    </div>
</body>

</html>